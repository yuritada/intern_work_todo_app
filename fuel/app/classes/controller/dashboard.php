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
        $boss_ranks = $this->quest_config['boss_ranks'];
        foreach ($active_bosses as &$boss)
        {
            $rank = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : $boss_ranks[1];
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
    public function action_project($id = null)
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
        $boss_ranks = $this->quest_config['boss_ranks'];
        foreach ($bosses as &$boss)
        {
            $rank = isset($boss_ranks[$boss['boss_rank']]) ? $boss_ranks[$boss['boss_rank']] : $boss_ranks[1];
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
                        'deadline' => $input['deadline'] ?: null,
                        'memo'     => $input['memo'] ?: null,
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
    public function action_edit($id = null)
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
                        'deadline' => $input['deadline'] ?: null,
                        'memo'     => $input['memo'] ?: null,
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
     * プロジェクト削除
     *
     * @param int $id プロジェクトID
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
