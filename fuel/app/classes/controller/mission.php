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
    public function action_detail($id = null)
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
        $boss_ranks = $this->quest_config['boss_ranks'];
        $rank_info = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : $boss_ranks[1];

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
     * @param int $project_id プロジェクトID
     */
    public function action_create($project_id = null)
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

        $data = array(
            'project'    => $project,
            'boss_ranks' => $this->quest_config['boss_ranks'],
            'errors'     => array(),
            'input'      => array(
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

                // バリデーション
                if (empty($input['title']))
                {
                    $data['errors'][] = 'ボス名を入力してください。';
                }

                if (empty($data['errors']))
                {
                    $boss_id = \Model_Task::create_parent(array(
                        'project_id' => $project_id,
                        'title'      => $input['title'],
                        'boss_rank'  => $input['boss_rank'],
                        'deadline'   => $input['deadline'] ?: null,
                    ));

                    if ($boss_id)
                    {
                        \Session::set_flash('success', 'ボス「' . $input['title'] . '」が出現しました！');
                        \Response::redirect('mission/detail/' . $boss_id);
                    }
                    else
                    {
                        $data['errors'][] = 'ボスの作成に失敗しました。';
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
    public function action_edit($id = null)
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

        $data = array(
            'boss'       => $boss,
            'boss_ranks' => $this->quest_config['boss_ranks'],
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
                        'deadline'  => $input['deadline'] ?: null,
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
    public function action_delete($id = null)
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
     * 子タスク（攻撃アクション）を追加
     *
     * @param int $parent_id 親タスクID
     */
    public function action_add_task($parent_id = null)
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
    public function action_delete_task($id = null)
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
