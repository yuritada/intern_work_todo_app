<?php
/**
 * Quest-Logic ミッションコントローラー (Controller_Mission)
 *
 * ボス（親タスク）の詳細表示と攻撃（子タスク）の管理を担当します。
 * Knockout.js と連携してリアルタイムなHP更新を実現します。
 */
class Controller_Mission extends Controller_Base
{
    /**
     * ミッション（ボス）詳細画面
     *
     * @param int $id 親タスク（ボス）のID
     */
    public function action_detail($id = '')
    {
        if ( ! $id)
        {
            \Session::set_flash('error', 'ミッションが指定されていません。');
            \Response::redirect('dashboard');
        }

        // 親タスクと子タスクを取得
        $boss = \Model_Task::find_parent_with_children($id, $this->current_user['id']);

        if ( ! $boss)
        {
            \Session::set_flash('error', '指定されたミッションが見つかりません。');
            \Response::redirect('dashboard');
        }

        // ボスランク情報を取得
        // 【Null安全化】boss_ranks キーが存在しない場合に備え、空配列をデフォルトに
        $boss_ranks = $this->quest_config['boss_ranks'] ?? array();
        // 【Null安全化】ランク情報が存在しない場合のフォールバック
        $rank_info = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : (isset($boss_ranks[1]) ? $boss_ranks[1] : array('label' => '不明', 'hp_multiplier' => 1));

        $data = array(
            'boss'      => $boss,
            'rank_info' => $rank_info,
        );

        $this->template->title = $boss['title'] . ' - Mission';
        $this->template->content = \View::forge('mission/detail', $data);
    }

    /**
     * 新規ボス作成フォーム表示
     *
     * 【Phase 5: UX向上】
     * ボス作成時に攻撃も一緒に登録できるように改善。
     * 自動バランスモードで攻撃力を自動配分。
     *
     * @param int $project_id プロジェクトID
     */
    public function action_create($project_id = '')
    {
        if ( ! $project_id)
        {
            \Session::set_flash('error', 'プロジェクトが指定されていません。');
            \Response::redirect('dashboard');
        }

        // プロジェクトの所有者確認
        $project = \Model_Project::find_by_id($project_id, $this->current_user['id']);
        if ( ! $project)
        {
            \Session::set_flash('error', 'プロジェクトが見つかりません。');
            \Response::redirect('dashboard');
        }

        // 設定を取得
        $boss_ranks = $this->quest_config['boss_ranks'] ?? array();
        $psychological_weights = $this->quest_config['psychological_weights'] ?? array(
            'easy'   => array('label' => '簡単', 'weight' => 5),
            'normal' => array('label' => '普通', 'weight' => 15),
            'hard'   => array('label' => 'きつい', 'weight' => 35),
        );

        $data = array(
            'project'              => $project,
            'boss_ranks'           => $boss_ranks,
            'psychological_weights' => $psychological_weights,
            'errors'               => array(),
            'input'                => array(
                'title'     => '',
                'boss_rank' => 1,
                'deadline'  => '',
            ),
        );

        if (\Input::method() === 'POST')
        {
            if ( ! \Security::check_token())
            {
                $data['errors'][] = '不正なリクエストです。';
            }
            else
            {
                $input = array(
                    'title'     => \Input::post('title', ''),
                    'boss_rank' => \Input::post('boss_rank', 1),
                    'deadline'  => \Input::post('deadline', ''),
                );
                $data['input'] = $input;

                $tasks_input = \Input::post('tasks', array());
                $auto_balance = \Input::post('auto_balance', 0);

                // バリデーション
                if (empty($input['title']))
                {
                    $data['errors'][] = 'ボス名を入力してください。';
                }

                if (empty($data['errors']))
                {
                    \DB::start_transaction();

                    try
                    {
                        // ボスを作成
                        $boss_id = \Model_Task::create_parent(array(
                            'project_id' => $project_id,
                            'title'      => $input['title'],
                            'boss_rank'  => $input['boss_rank'],
                            'deadline'   => $input['deadline'] ?: '',
                        ));

                        if ( ! $boss_id)
                        {
                            throw new \Exception('ボスの作成に失敗しました。');
                        }

                        // ボスのHPを取得
                        $boss = \Model_Task::find_parent_by_id($boss_id);
                        $boss_hp = $boss ? $boss['boss_hp'] : 100;

                        // 攻撃を登録
                        $valid_tasks = array();
                        foreach ($tasks_input as $task)
                        {
                            $title = isset($task['title']) ? trim($task['title']) : '';
                            if ( ! empty($title))
                            {
                                $weight_key = isset($task['weight']) ? $task['weight'] : 'normal';
                                $weight = isset($psychological_weights[$weight_key]['weight'])
                                    ? $psychological_weights[$weight_key]['weight']
                                    : 15;

                                $valid_tasks[] = array(
                                    'title'      => $title,
                                    'weight_key' => $weight_key,
                                    'weight'     => $weight,
                                );
                            }
                        }

                        if ( ! empty($valid_tasks))
                        {
                            // 自動バランスモード：比率で分配
                            if ($auto_balance)
                            {
                                $total_ratio = 0;
                                foreach ($valid_tasks as $t)
                                {
                                    $total_ratio += $t['weight'];
                                }

                                $floor_sum = 0;
                                $calculated_weights = array();
                                foreach ($valid_tasks as $i => $t)
                                {
                                    $w = ($total_ratio > 0) ? (int)floor($boss_hp * $t['weight'] / $total_ratio) : 1;
                                    $calculated_weights[$i] = $w;
                                    $floor_sum += $w;
                                }

                                // 端数を最初のタスクに加算
                                $remainder = $boss_hp - $floor_sum;
                                if ($remainder > 0 && count($calculated_weights) > 0)
                                {
                                    $calculated_weights[0] += $remainder;
                                }

                                foreach ($valid_tasks as $i => $t)
                                {
                                    $valid_tasks[$i]['weight'] = max(1, $calculated_weights[$i]);
                                }
                            }

                            // タスクを登録
                            foreach ($valid_tasks as $t)
                            {
                                \Model_Task::create_child(array(
                                    'parent_id' => $boss_id,
                                    'title'     => $t['title'],
                                    'weight'    => $t['weight'],
                                ));
                            }
                        }

                        \DB::commit_transaction();

                        $message = 'ボス「' . $input['title'] . '」が出現しました！';
                        if (count($valid_tasks) > 0)
                        {
                            $message .= '（' . count($valid_tasks) . '件の攻撃を登録）';
                        }
                        \Session::set_flash('success', $message);
                        \Response::redirect('mission/detail/' . $boss_id);

                    }
                    catch (\Exception $e)
                    {
                        \DB::rollback_transaction();
                        $data['errors'][] = $e->getMessage();
                    }
                }
            }
        }

        $this->template->title = '新しいボスを追加';
        $this->template->content = \View::forge('mission/create', $data);
    }

    /**
     * ボス編集
     *
     * @param int $id 親タスクID
     */
    public function action_edit($id = '')
    {
        if ( ! $id)
        {
            \Response::redirect('dashboard');
        }

        $boss = \Model_Task::find_parent_by_id($id, $this->current_user['id']);
        if ( ! $boss)
        {
            \Session::set_flash('error', 'ボスが見つかりません。');
            \Response::redirect('dashboard');
        }

        // 【Null安全化】boss_ranks キーが存在しない場合に備え、空配列をデフォルトに
        $data = array(
            'boss'       => $boss,
            'boss_ranks' => $this->quest_config['boss_ranks'] ?? array(),
            'errors'     => array(),
            'input'      => array(
                'title'     => $boss['title'],
                'boss_rank' => $boss['boss_rank'],
                'deadline'  => $boss['deadline'],
            ),
        );

        if (\Input::method() === 'POST')
        {
            if ( ! \Security::check_token())
            {
                $data['errors'][] = '不正なリクエストです。';
            }
            else
            {
                $input = array(
                    'title'     => \Input::post('title', ''),
                    'boss_rank' => \Input::post('boss_rank', 1),
                    'deadline'  => \Input::post('deadline', ''),
                );
                $data['input'] = $input;

                if (empty($input['title']))
                {
                    $data['errors'][] = 'ボス名を入力してください。';
                }

                if (empty($data['errors']))
                {
                    $updated = \Model_Task::update_parent($id, array(
                        'title'     => $input['title'],
                        'boss_rank' => $input['boss_rank'],
                        'deadline'  => $input['deadline'] ?: '',
                    ), $this->current_user['id']);

                    if ($updated)
                    {
                        \Session::set_flash('success', 'ボス情報を更新しました。');
                        \Response::redirect('mission/detail/' . $id);
                    }
                    else
                    {
                        $data['errors'][] = '更新に失敗しました。';
                    }
                }
            }
        }

        $this->template->title = 'ボス編集';
        $this->template->content = \View::forge('mission/edit', $data);
    }

    /**
     * ボス削除
     *
     * @param int $id 親タスクID
     */
    public function action_delete($id = '')
    {
        if ( ! $id || \Input::method() !== 'POST')
        {
            \Response::redirect('dashboard');
        }

        if ( ! \Security::check_token())
        {
            \Session::set_flash('error', '不正なリクエストです。');
            \Response::redirect('dashboard');
        }

        $boss = \Model_Task::find_parent_by_id($id, $this->current_user['id']);
        if ( ! $boss)
        {
            \Session::set_flash('error', 'ボスが見つかりません。');
            \Response::redirect('dashboard');
        }

        $project_id = $boss['project_id'];

        if (\Model_Task::delete_parent($id, $this->current_user['id']))
        {
            \Session::set_flash('success', 'ボス「' . $boss['title'] . '」を削除しました。');
        }
        else
        {
            \Session::set_flash('error', '削除に失敗しました。');
        }

        \Response::redirect('dashboard/project/' . $project_id);
    }

    /**
     * タスク一斉登録画面
     *
     * 【Phase 5: UX向上 - 一斉登録】
     * テキストベースで高速にタスクを追加できる専用ページです。
     * 「心理的重み」選択により、適切なweightが自動設定されます。
     *
     * @param int $parent_id 親タスク（ボス）ID
     */
    public function action_bulk($parent_id = '')
    {
        if ( ! $parent_id)
        {
            \Session::set_flash('error', 'ボスが指定されていません。');
            \Response::redirect('dashboard');
        }

        // 親タスクと子タスクを取得
        $boss = \Model_Task::find_parent_with_children($parent_id, $this->current_user['id']);

        if ( ! $boss)
        {
            \Session::set_flash('error', '指定されたボスが見つかりません。');
            \Response::redirect('dashboard');
        }

        // ボスランク情報を取得
        $boss_ranks = $this->quest_config['boss_ranks'] ?? array();
        $rank_info = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : array('label' => '不明');

        // 心理的重み設定を取得
        $psychological_weights = $this->quest_config['psychological_weights'] ?? array(
            'easy'   => array('label' => '簡単', 'weight' => 5, 'description' => 'すぐに終わる軽いタスク'),
            'normal' => array('label' => '普通', 'weight' => 15, 'description' => '通常の作業量のタスク'),
            'hard'   => array('label' => 'きつい', 'weight' => 35, 'description' => '時間がかかる重いタスク'),
        );

        // 現在の合計weight計算
        $current_total_weight = \Logic\Battle::get_total_weight($parent_id);

        $data = array(
            'boss'                  => $boss,
            'rank_info'             => $rank_info,
            'psychological_weights' => $psychological_weights,
            'current_total_weight'  => $current_total_weight,
            'errors'                => array(),
        );

        // POST処理
        if (\Input::method() === 'POST')
        {
            if ( ! \Security::check_token())
            {
                $data['errors'][] = '不正なリクエストです。';
            }
            else
            {
                $titles = \Input::post('titles', array());
                $weights = \Input::post('weights', array());
                $auto_balance = \Input::post('auto_balance', 0);

                // 有効なタスク（タイトルが入力されているもの）を抽出
                $valid_titles = array();
                foreach ($titles as $i => $title)
                {
                    $title = trim($title);
                    if ( ! empty($title))
                    {
                        $valid_titles[$i] = $title;
                    }
                }

                if (empty($valid_titles))
                {
                    $data['errors'][] = '少なくとも1つのタスクを入力してください。';
                }
                else
                {
                    // タスクデータを構築
                    $tasks = array();

                    if ($auto_balance)
                    {
                        // 【自動バランスモード】
                        // 心理的重みの比率で残りHPを分配
                        $remaining_hp = max(0, $boss['boss_hp'] - $current_total_weight);

                        // 各タスクの心理的重み（比率）を取得
                        $task_ratios = array();
                        $total_ratio = 0;
                        foreach ($valid_titles as $i => $title)
                        {
                            $weight_key = isset($weights[$i]) ? $weights[$i] : 'normal';
                            $ratio = isset($psychological_weights[$weight_key]['weight'])
                                ? $psychological_weights[$weight_key]['weight']
                                : 15;
                            $task_ratios[$i] = $ratio;
                            $total_ratio += $ratio;
                        }

                        // 比率に基づいてweightを計算
                        $floor_sum = 0;
                        $calculated_weights = array();
                        foreach ($valid_titles as $i => $title)
                        {
                            $ratio = $task_ratios[$i];
                            $weight = ($total_ratio > 0) ? (int)floor($remaining_hp * $ratio / $total_ratio) : 1;
                            $calculated_weights[$i] = $weight;
                            $floor_sum += $weight;
                        }

                        // 端数を最初のタスクに加算
                        $remainder = $remaining_hp - $floor_sum;
                        $first_key = array_key_first($valid_titles);
                        if ($first_key !== null && $remainder > 0)
                        {
                            $calculated_weights[$first_key] += $remainder;
                        }

                        // タスク配列を構築
                        foreach ($valid_titles as $i => $title)
                        {
                            $weight = max(1, $calculated_weights[$i]); // 最低1ダメージ
                            $tasks[] = array(
                                'title'  => $title,
                                'weight' => $weight,
                            );
                        }
                    }
                    else
                    {
                        // 【手動モード】
                        // 心理的重みから攻撃力を取得
                        foreach ($valid_titles as $i => $title)
                        {
                            $weight_key = isset($weights[$i]) ? $weights[$i] : 'normal';
                            $weight = isset($psychological_weights[$weight_key]['weight'])
                                ? $psychological_weights[$weight_key]['weight']
                                : 15;

                            $tasks[] = array(
                                'title'  => $title,
                                'weight' => $weight,
                            );
                        }
                    }

                    // 一括登録実行
                    $result = \Logic\Battle::bulk_insert_tasks($parent_id, $tasks);

                    if ($result['success'])
                    {
                        $message = $result['count'] . '件の攻撃を追加しました！';
                        if ($auto_balance)
                        {
                            $message .= ' （自動バランスで配分）';
                        }
                        \Session::set_flash('success', $message);
                        \Response::redirect('mission/detail/' . $parent_id);
                    }
                    else
                    {
                        $data['errors'][] = $result['error'] ?: '登録に失敗しました。';
                    }
                }
            }
        }

        $this->template->title = '攻撃を一斉登録 - ' . $boss['title'];
        $this->template->content = \View::forge('mission/bulk', $data);
    }

    /**
     * 攻撃編集画面
     *
     * 【Phase 5: UX向上 - 攻撃全体編集】
     * 既存の攻撃を表示し、追加・削除・ダメージ再計算ができる画面です。
     *
     * @param int $parent_id 親タスク（ボス）ID
     */
    public function action_edit_attacks($parent_id = '')
    {
        if ( ! $parent_id)
        {
            \Session::set_flash('error', 'ボスが指定されていません。');
            \Response::redirect('dashboard');
        }

        // 親タスクと子タスクを取得
        $boss = \Model_Task::find_parent_with_children($parent_id, $this->current_user['id']);

        if ( ! $boss)
        {
            \Session::set_flash('error', '指定されたボスが見つかりません。');
            \Response::redirect('dashboard');
        }

        // ボスランク情報を取得
        $boss_ranks = $this->quest_config['boss_ranks'] ?? array();
        $rank_info = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : array('label' => '不明');

        // 心理的重み設定を取得
        $psychological_weights = $this->quest_config['psychological_weights'] ?? array(
            'easy'   => array('label' => '簡単', 'weight' => 5, 'description' => 'すぐに終わる軽いタスク'),
            'normal' => array('label' => '普通', 'weight' => 15, 'description' => '通常の作業量のタスク'),
            'hard'   => array('label' => 'きつい', 'weight' => 35, 'description' => '時間がかかる重いタスク'),
        );

        $data = array(
            'boss'                  => $boss,
            'rank_info'             => $rank_info,
            'psychological_weights' => $psychological_weights,
            'errors'                => array(),
        );

        // POST処理
        if (\Input::method() === 'POST')
        {
            if ( ! \Security::check_token())
            {
                $data['errors'][] = '不正なリクエストです。';
            }
            else
            {
                $tasks_input = \Input::post('tasks', array());
                $auto_balance = \Input::post('auto_balance', 0);

                \DB::start_transaction();

                try
                {
                    // 既存の子タスクを全て論理削除
                    $now = date('Y-m-d H:i:s');
                    \DB::update('child_tasks')
                        ->set(array(
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ))
                        ->where('parent_id', '=', $parent_id)
                        ->where('deleted_at', 'IS', \DB::expr('NULL'))
                        ->execute();

                    // 新しいタスクを登録
                    $valid_tasks = array();
                    foreach ($tasks_input as $task)
                    {
                        $title = isset($task['title']) ? trim($task['title']) : '';
                        if ( ! empty($title))
                        {
                            $weight_key = isset($task['weight']) ? $task['weight'] : 'normal';
                            $weight = isset($psychological_weights[$weight_key]['weight'])
                                ? $psychological_weights[$weight_key]['weight']
                                : 15;
                            $done = isset($task['done']) && $task['done'] ? 1 : 0;

                            $valid_tasks[] = array(
                                'title'      => $title,
                                'weight_key' => $weight_key,
                                'weight'     => $weight,
                                'done'       => $done,
                            );
                        }
                    }

                    if (empty($valid_tasks))
                    {
                        // タスクが0件の場合もOK（ボスのHP復元）
                        // current_hpをboss_hpにリセット
                        \DB::update('parent_tasks')
                            ->set(array(
                                'current_hp' => $boss['boss_hp'],
                                'done'       => 0,
                                'updated_at' => $now,
                            ))
                            ->where('id', '=', $parent_id)
                            ->execute();
                    }
                    else
                    {
                        // 自動バランスモード：比率で分配
                        if ($auto_balance)
                        {
                            $total_ratio = 0;
                            foreach ($valid_tasks as $t)
                            {
                                $total_ratio += $t['weight'];
                            }

                            $floor_sum = 0;
                            $calculated_weights = array();
                            foreach ($valid_tasks as $i => $t)
                            {
                                $w = ($total_ratio > 0) ? (int)floor($boss['boss_hp'] * $t['weight'] / $total_ratio) : 1;
                                $calculated_weights[$i] = $w;
                                $floor_sum += $w;
                            }

                            // 端数を最初のタスクに加算
                            $remainder = $boss['boss_hp'] - $floor_sum;
                            if ($remainder > 0 && count($calculated_weights) > 0)
                            {
                                $calculated_weights[0] += $remainder;
                            }

                            foreach ($valid_tasks as $i => $t)
                            {
                                $valid_tasks[$i]['weight'] = max(1, $calculated_weights[$i]);
                            }
                        }

                        // タスクを登録し、ダメージを計算
                        $total_damage = 0;
                        $completed_damage = 0;

                        foreach ($valid_tasks as $t)
                        {
                            \DB::insert('child_tasks')
                                ->set(array(
                                    'parent_id'  => (int)$parent_id,
                                    'title'      => $t['title'],
                                    'weight'     => $t['weight'],
                                    'done'       => $t['done'],
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ))
                                ->execute();

                            $total_damage += $t['weight'];
                            if ($t['done'])
                            {
                                $completed_damage += $t['weight'];
                            }
                        }

                        // ボスのHPを再計算
                        $new_current_hp = max(0, $boss['boss_hp'] - $completed_damage);
                        $is_dead = ($new_current_hp <= 0);

                        \DB::update('parent_tasks')
                            ->set(array(
                                'current_hp' => $new_current_hp,
                                'done'       => $is_dead ? 1 : 0,
                                'updated_at' => $now,
                            ))
                            ->where('id', '=', $parent_id)
                            ->execute();
                    }

                    \DB::commit_transaction();

                    \Session::set_flash('success', '攻撃を更新しました。');
                    \Response::redirect('mission/detail/' . $parent_id);

                }
                catch (\Exception $e)
                {
                    \DB::rollback_transaction();
                    $data['errors'][] = $e->getMessage();
                }
            }
        }

        $this->template->title = '攻撃を編集 - ' . $boss['title'];
        $this->template->content = \View::forge('mission/edit_attacks', $data);
    }

    /**
     * 子タスク（攻撃アクション）を追加
     *
     * @param int $parent_id 親タスクID
     */
    public function action_add_task($parent_id = '')
    {
        if ( ! $parent_id || \Input::method() !== 'POST')
        {
            \Response::redirect('dashboard');
        }

        if ( ! \Security::check_token())
        {
            \Session::set_flash('error', '不正なリクエストです。');
            \Response::redirect('mission/detail/' . $parent_id);
        }

        // 親タスクの所有者確認
        $boss = \Model_Task::find_parent_by_id($parent_id, $this->current_user['id']);
        if ( ! $boss)
        {
            \Session::set_flash('error', 'ボスが見つかりません。');
            \Response::redirect('dashboard');
        }

        $title = \Input::post('title', '');
        $weight = \Input::post('weight', 10);

        if (empty($title))
        {
            \Session::set_flash('error', 'タスク名を入力してください。');
            \Response::redirect('mission/detail/' . $parent_id);
        }

        $task_id = \Model_Task::create_child(array(
            'parent_id' => $parent_id,
            'title'     => $title,
            'weight'    => $weight,
        ));

        if ($task_id)
        {
            \Session::set_flash('success', '攻撃「' . $title . '」を追加しました。');
        }
        else
        {
            \Session::set_flash('error', 'タスクの追加に失敗しました。');
        }

        \Response::redirect('mission/detail/' . $parent_id);
    }

    /**
     * 子タスクを削除
     *
     * @param int $id 子タスクID
     */
    public function action_delete_task($id = '')
    {
        if ( ! $id || \Input::method() !== 'POST')
        {
            \Response::redirect('dashboard');
        }

        if ( ! \Security::check_token())
        {
            \Session::set_flash('error', '不正なリクエストです。');
            \Response::redirect('dashboard');
        }

        $task = \Model_Task::find_child_by_id($id, $this->current_user['id']);
        if ( ! $task)
        {
            \Session::set_flash('error', 'タスクが見つかりません。');
            \Response::redirect('dashboard');
        }

        $parent_id = $task['parent_id'];

        if (\Model_Task::delete_child($id, $this->current_user['id']))
        {
            \Session::set_flash('success', 'タスクを削除しました。');
        }
        else
        {
            \Session::set_flash('error', '削除に失敗しました。');
        }

        \Response::redirect('mission/detail/' . $parent_id);
    }
}
