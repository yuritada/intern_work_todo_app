<?php
/**
 * Quest-Logic ダッシュボードコントローラー (Controller_Dashboard)
 *
 * 冒険の拠点となるダッシュボード画面を担当します。
 * プロジェクト一覧、遭遇中のボス、進捗状況などを表示します。
 */
class Controller_Dashboard extends Controller_Base
{
    /**
     * ダッシュボード（トップページ）
     *
     * 現在遭遇中のボス（締切の近い親タスク）や
     * プロジェクトの進行状況を表示します。
     */
    public function action_index()
    {
        // ユーザーのプロジェクト一覧を取得
        $projects = \Model_Project::find_by_user($this->current_user['id']);

        // 各プロジェクトの進捗情報を追加
        foreach ($projects as &$project)
        {
            $project['progress'] = \Model_Project::get_progress($project['id']);
        }
        unset($project);

        // 遭遇中のボス（未討伐で締切の近い順）を取得
        $active_bosses = \Model_Task::find_active_bosses($this->current_user['id'], 5);

        // ボスランク情報を付加
        // 【Null安全化】boss_ranks キーが存在しない場合に備え、空配列をデフォルトに
        $boss_ranks = $this->quest_config['boss_ranks'] ?? array();
        foreach ($active_bosses as &$boss)
        {
            // 【Null安全化】ランク情報が存在しない場合のフォールバック
            $rank = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : (isset($boss_ranks[1]) ? $boss_ranks[1] : array('label' => '不明'));
            $boss['rank_label'] = $rank['label'];
            $boss['hp_percent'] = $boss['boss_hp'] > 0
                ? round(($boss['current_hp'] / $boss['boss_hp']) * 100)
                : 0;
        }
        unset($boss);

        $data = array(
            'projects'      => $projects,
            'active_bosses' => $active_bosses,
            'boss_ranks'    => $boss_ranks,
        );

        $this->template->title = 'Dashboard';
        $this->template->content = \View::forge('dashboard/index', $data);
    }

    /**
     * プロジェクト詳細画面
     *
     * @param int $id プロジェクトID
     */
    public function action_project($id = '')
    {
        if ( ! $id)
        {
            \Session::set_flash('error', 'プロジェクトが指定されていません。');
            \Response::redirect('dashboard');
        }

        // プロジェクトを取得（所有者確認）
        $project = \Model_Project::find_by_id($id, $this->current_user['id']);
        if ( ! $project)
        {
            \Session::set_flash('error', 'プロジェクトが見つかりません。');
            \Response::redirect('dashboard');
        }

        // プロジェクトのボス一覧を取得
        $bosses = \Model_Task::find_parents_by_project($id);

        // ボスランク情報を付加
        // 【Null安全化】boss_ranks キーが存在しない場合に備え、空配列をデフォルトに
        $boss_ranks = $this->quest_config['boss_ranks'] ?? array();
        foreach ($bosses as &$boss)
        {
            // 【Null安全化】ランク情報が存在しない場合のフォールバック
            $rank = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : (isset($boss_ranks[1]) ? $boss_ranks[1] : array('label' => '不明'));
            $boss['rank_label'] = $rank['label'];
            $boss['hp_percent'] = $boss['boss_hp'] > 0
                ? round(($boss['current_hp'] / $boss['boss_hp']) * 100)
                : 0;
        }
        unset($boss);

        // 進捗情報を取得
        $progress = \Model_Project::get_progress($id);

        $data = array(
            'project'    => $project,
            'bosses'     => $bosses,
            'progress'   => $progress,
            'boss_ranks' => $boss_ranks,
        );

        $this->template->title = $project['title'] . ' - Project';
        $this->template->content = \View::forge('dashboard/project', $data);
    }

    /**
     * プロジェクト作成
     */
    public function action_create()
    {
        $data = array(
            'errors' => array(),
            'input'  => array(
                'title'    => '',
                'deadline' => '',
                'memo'     => '',
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
                    'title'    => \Input::post('title', ''),
                    'deadline' => \Input::post('deadline', ''),
                    'memo'     => \Input::post('memo', ''),
                );
                $data['input'] = $input;

                // バリデーション
                if (empty($input['title']))
                {
                    $data['errors'][] = 'プロジェクト名を入力してください。';
                }

                if (empty($data['errors']))
                {
                    $project_id = \Model_Project::create(array(
                        'user_id'  => $this->current_user['id'],
                        'title'    => $input['title'],
                        'deadline' => $input['deadline'] ?: '',
                        'memo'     => $input['memo'] ?: '',
                    ));

                    if ($project_id)
                    {
                        \Session::set_flash('success', '新しい冒険「' . $input['title'] . '」を開始しました！');
                        \Response::redirect('dashboard/project/' . $project_id);
                    }
                    else
                    {
                        $data['errors'][] = 'プロジェクトの作成に失敗しました。';
                    }
                }
            }
        }

        $this->template->title = '新しい冒険を開始';
        $this->template->content = \View::forge('dashboard/create', $data);
    }

    /**
     * プロジェクト編集
     *
     * @param int $id プロジェクトID
     */
    public function action_edit($id = '')
    {
        if ( ! $id)
        {
            \Response::redirect('dashboard');
        }

        $project = \Model_Project::find_by_id($id, $this->current_user['id']);
        if ( ! $project)
        {
            \Session::set_flash('error', 'プロジェクトが見つかりません。');
            \Response::redirect('dashboard');
        }

        $data = array(
            'project' => $project,
            'errors'  => array(),
            'input'   => array(
                'title'    => $project['title'],
                'deadline' => $project['deadline'],
                'memo'     => $project['memo'],
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
                    'title'    => \Input::post('title', ''),
                    'deadline' => \Input::post('deadline', ''),
                    'memo'     => \Input::post('memo', ''),
                );
                $data['input'] = $input;

                if (empty($input['title']))
                {
                    $data['errors'][] = 'プロジェクト名を入力してください。';
                }

                if (empty($data['errors']))
                {
                    $updated = \Model_Project::update($id, array(
                        'title'    => $input['title'],
                        'deadline' => $input['deadline'] ?: '',
                        'memo'     => $input['memo'] ?: '',
                    ), $this->current_user['id']);

                    if ($updated)
                    {
                        \Session::set_flash('success', 'プロジェクト情報を更新しました。');
                        \Response::redirect('dashboard/project/' . $id);
                    }
                    else
                    {
                        $data['errors'][] = '更新に失敗しました。';
                    }
                }
            }
        }

        $this->template->title = 'プロジェクト編集';
        $this->template->content = \View::forge('dashboard/edit', $data);
    }

    /**
     * プロジェクト一斉登録（ボス＆攻撃）
     *
     * 【Phase 5: UX向上】
     * プロジェクト画面から複数のボスと、それぞれに属する攻撃を
     * 一度に登録できる機能です。
     *
     * @param int $id プロジェクトID
     */
    public function action_bulk_create($id = '')
    {
        if ( ! $id)
        {
            \Session::set_flash('error', 'プロジェクトが指定されていません。');
            \Response::redirect('dashboard');
        }

        $project = \Model_Project::find_by_id($id, $this->current_user['id']);
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
                $bosses_data = \Input::post('bosses', array());
                $auto_balance = \Input::post('auto_balance', 0);

                if (empty($bosses_data))
                {
                    $data['errors'][] = '少なくとも1つのボスを入力してください。';
                }
                else
                {
                    \DB::start_transaction();

                    try
                    {
                        $created_bosses = 0;
                        $created_tasks = 0;

                        foreach ($bosses_data as $boss_input)
                        {
                            $boss_title = isset($boss_input['title']) ? trim($boss_input['title']) : '';
                            if (empty($boss_title))
                            {
                                continue; // タイトルが空のボスはスキップ
                            }

                            $boss_rank = isset($boss_input['rank']) ? (int)$boss_input['rank'] : 1;

                            // ボスを作成
                            $boss_id = \Model_Task::create_parent(array(
                                'project_id' => $id,
                                'title'      => $boss_title,
                                'boss_rank'  => $boss_rank,
                                'deadline'   => '',
                            ));

                            if ( ! $boss_id)
                            {
                                throw new \Exception('ボス「' . $boss_title . '」の作成に失敗しました。');
                            }

                            $created_bosses++;

                            // ボスのHPを取得
                            $boss = \Model_Task::find_parent_by_id($boss_id);
                            $boss_hp = $boss ? $boss['boss_hp'] : 100;

                            // 攻撃（子タスク）を処理
                            $attacks = isset($boss_input['attacks']) ? $boss_input['attacks'] : array();
                            $valid_attacks = array();

                            foreach ($attacks as $attack)
                            {
                                $attack_title = isset($attack['title']) ? trim($attack['title']) : '';
                                if ( ! empty($attack_title))
                                {
                                    $weight_key = isset($attack['weight']) ? $attack['weight'] : 'normal';
                                    $valid_attacks[] = array(
                                        'title'      => $attack_title,
                                        'weight_key' => $weight_key,
                                        'weight'     => isset($psychological_weights[$weight_key]['weight'])
                                            ? $psychological_weights[$weight_key]['weight']
                                            : 15,
                                    );
                                }
                            }

                            if ( ! empty($valid_attacks))
                            {
                                if ($auto_balance)
                                {
                                    // 自動バランスモード：比率で分配
                                    $total_ratio = 0;
                                    foreach ($valid_attacks as $a)
                                    {
                                        $total_ratio += $a['weight'];
                                    }

                                    $floor_sum = 0;
                                    $calculated_weights = array();
                                    foreach ($valid_attacks as $i => $a)
                                    {
                                        $w = ($total_ratio > 0) ? (int)floor($boss_hp * $a['weight'] / $total_ratio) : 1;
                                        $calculated_weights[$i] = $w;
                                        $floor_sum += $w;
                                    }

                                    // 端数を最初のタスクに加算
                                    $remainder = $boss_hp - $floor_sum;
                                    if ($remainder > 0 && count($calculated_weights) > 0)
                                    {
                                        $calculated_weights[0] += $remainder;
                                    }

                                    foreach ($valid_attacks as $i => $a)
                                    {
                                        \Model_Task::create_child(array(
                                            'parent_id' => $boss_id,
                                            'title'     => $a['title'],
                                            'weight'    => max(1, $calculated_weights[$i]),
                                        ));
                                        $created_tasks++;
                                    }
                                }
                                else
                                {
                                    // 手動モード：固定weight
                                    foreach ($valid_attacks as $a)
                                    {
                                        \Model_Task::create_child(array(
                                            'parent_id' => $boss_id,
                                            'title'     => $a['title'],
                                            'weight'    => $a['weight'],
                                        ));
                                        $created_tasks++;
                                    }
                                }
                            }
                        }

                        if ($created_bosses === 0)
                        {
                            throw new \Exception('有効なボスがありません。');
                        }

                        \DB::commit_transaction();

                        $message = $created_bosses . '体のボス';
                        if ($created_tasks > 0)
                        {
                            $message .= '、' . $created_tasks . '件の攻撃';
                        }
                        $message .= 'を登録しました！';

                        \Session::set_flash('success', $message);
                        \Response::redirect('dashboard/project/' . $id);

                    }
                    catch (\Exception $e)
                    {
                        \DB::rollback_transaction();
                        $data['errors'][] = $e->getMessage();
                    }
                }
            }
        }

        $this->template->title = '一斉登録 - ' . $project['title'];
        $this->template->content = \View::forge('dashboard/bulk_create', $data);
    }

    /**
     * プロジェクト削除
     *
     * @param int $id プロジェクトID
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

        $project = \Model_Project::find_by_id($id, $this->current_user['id']);
        if ( ! $project)
        {
            \Session::set_flash('error', 'プロジェクトが見つかりません。');
            \Response::redirect('dashboard');
        }

        if (\Model_Project::delete($id, $this->current_user['id']))
        {
            \Session::set_flash('success', '冒険「' . $project['title'] . '」を終了しました。');
        }
        else
        {
            \Session::set_flash('error', '削除に失敗しました。');
        }

        \Response::redirect('dashboard');
    }
}
