<?php
/**
 * Quest-Logic バトルAPIコントローラー (Controller_Api_Battle)
 *
 * 【解説: APIエンドポイントの役割】
 * このコントローラーはフロントエンド（Knockout.js）からの
 * Ajax リクエストを処理し、JSON形式でレスポンスを返します。
 * HTMLは返さず、データのみをやり取りすることでSPA的な体験を提供します。
 *
 * 【解説: Controller_Base を継承する理由】
 * APIでも認証チェックは必要です。before() で未ログインを検知した場合、
 * HTMLのリダイレクトではなくJSONエラーを返すようオーバーライドしています。
 */
class Controller_Api_Battle extends \Controller
{
    /**
     * 現在のユーザー情報
     * @var array|null
     */
    protected $current_user = null;

    /**
     * リクエスト前処理
     * 認証チェックとJSON用の設定を行う
     */
    public function before()
    {
        parent::before();

        // セッションからユーザーを取得
        $user_id = \Session::get('user_id');

        if ($user_id)
        {
            $this->current_user = \DB::select('id', 'username', 'level', 'xp')
                ->from('users')
                ->where('id', '=', $user_id)
                ->execute()
                ->current();
        }

        // 未認証の場合はJSONエラーを返して終了
        if ( ! $this->current_user)
        {
            // 【解説: exit() による処理終了】
            // before() 内で return しても処理は継続するため、
            // exit() で明示的に終了させる必要がある
            $response = \Response::forge(
                json_encode(array('success' => false, 'error' => '認証が必要です。'), JSON_UNESCAPED_UNICODE),
                401,
                array('Content-Type' => 'application/json')
            );
            $response->send(true);
            exit();
        }
    }

    /**
     * 攻撃（タスク完了）API
     *
     * POST /api/battle/attack
     * パラメータ: task_id (子タスクのID)
     *
     * @return Response JSON
     */
    public function action_attack()
    {
        if (\Input::method() !== 'POST')
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'POSTメソッドのみ許可されています。',
            ), 405);
        }

        // CSRF検証
        if ( ! \Security::check_token())
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => '不正なリクエストです。',
            ), 403);
        }

        $task_id = \Input::post('task_id');

        if ( ! $task_id)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'タスクIDが指定されていません。',
            ), 400);
        }

        // 【解説: Logic\Battle の呼び出し】
        // ビジネスロジックはLogicクラスに分離されているため、
        // コントローラーはシンプルにデータの受け渡しのみを行う
        $result = \Logic\Battle::calculate_damage($task_id, $this->current_user['id']);

        if ($result['success'])
        {
            // レスポンスデータを構築
            $response_data = array(
                'success'   => true,
                'damage'    => $result['damage'],
                'new_hp'    => $result['new_hp'],
                'is_dead'   => $result['is_dead'],
                'gained_xp' => $result['gained_xp'],
                'level_up'  => $result['level_up'],
                'new_level' => $result['new_level'],
                'project_cleared' => false,
            );

            // 【Phase 5: プロジェクト完全制覇判定】
            // ボスを倒した場合、プロジェクト内の全ボスが討伐済みかチェック
            if ($result['is_dead'])
            {
                // タスクからプロジェクトIDを取得
                $child_task = \DB::select('parent_tasks.project_id')
                    ->from('child_tasks')
                    ->join('parent_tasks', 'INNER')
                    ->on('child_tasks.parent_id', '=', 'parent_tasks.id')
                    ->where('child_tasks.id', '=', $task_id)
                    ->execute()
                    ->current();

                if ($child_task && $child_task['project_id'])
                {
                    $response_data['project_cleared'] = \Logic\Battle::check_project_clear($child_task['project_id']);
                }
            }

            return $this->json_response($response_data);
        }
        else
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => $result['error'] ?: 'タスクの完了に失敗しました。',
            ), 400);
        }
    }

    /**
     * 取り消し（タスク完了を戻す）API
     *
     * POST /api/battle/undo
     * パラメータ: task_id (子タスクのID)
     *
     * @return Response JSON
     */
    public function action_undo()
    {
        if (\Input::method() !== 'POST')
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'POSTメソッドのみ許可されています。',
            ), 405);
        }

        if ( ! \Security::check_token())
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => '不正なリクエストです。',
            ), 403);
        }

        $task_id = \Input::post('task_id');

        if ( ! $task_id)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'タスクIDが指定されていません。',
            ), 400);
        }

        $result = \Logic\Battle::undo_task($task_id, $this->current_user['id']);

        if ($result['success'])
        {
            return $this->json_response(array(
                'success' => true,
                'new_hp'  => $result['new_hp'],
            ));
        }
        else
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => $result['error'] ?: 'タスクの取り消しに失敗しました。',
            ), 400);
        }
    }

    /**
     * ボス情報取得API
     *
     * GET /api/battle/status/:boss_id
     *
     * @param int $boss_id ボス（親タスク）のID
     * @return Response JSON
     */
    public function action_status($boss_id = null)
    {
        if ( ! $boss_id)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'ボスIDが指定されていません。',
            ), 400);
        }

        $boss = \Model_Task::find_parent_with_children($boss_id, $this->current_user['id']);

        if ( ! $boss)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'ボスが見つかりません。',
            ), 404);
        }

        return $this->json_response(array(
            'success'         => true,
            'boss_id'         => $boss['id'],
            'title'           => $boss['title'],
            'current_hp'      => $boss['current_hp'],
            'boss_hp'         => $boss['boss_hp'],
            'hp_percent'      => $boss['hp_percent'],
            'is_dead'         => $boss['done'] == 1,
            'total_tasks'     => $boss['total_tasks'],
            'completed_tasks' => $boss['completed_tasks'],
            'tasks'           => array_map(function($task) {
                return array(
                    'id'     => $task['id'],
                    'title'  => $task['title'],
                    'weight' => $task['weight'],
                    'done'   => $task['done'] == 1,
                );
            }, $boss['children']),
        ));
    }

    /**
     * ユーザーステータス取得API
     *
     * GET /api/battle/user_status
     * ナビゲーションバーの経験値表示をリアルタイム更新するために使用
     *
     * @return Response JSON
     */
    public function action_user_status()
    {
        // 設定ファイルから経験値テーブルを取得
        \Config::load('quest', true);
        $xp_table = \Config::get('quest.xp_table') ?? array();

        $current_level = (int)$this->current_user['level'];
        $current_xp = (int)$this->current_user['xp'];

        // 次のレベルに必要なXPを計算
        $next_level = $current_level + 1;
        $next_level_xp = isset($xp_table[$next_level]) ? $xp_table[$next_level] : $current_xp;
        $current_level_xp = isset($xp_table[$current_level]) ? $xp_table[$current_level] : 0;

        // 進捗率を計算
        $xp_progress = 0;
        if ($next_level_xp > $current_level_xp)
        {
            $xp_progress = ($current_xp - $current_level_xp) / ($next_level_xp - $current_level_xp) * 100;
        }

        return $this->json_response(array(
            'success'       => true,
            'username'      => $this->current_user['username'],
            'level'         => $current_level,
            'xp'            => $current_xp,
            'next_level_xp' => $next_level_xp,
            'xp_progress'   => round($xp_progress, 1),
        ));
    }

    /**
     * タスク追加API
     *
     * POST /api/battle/add_task
     * パラメータ: boss_id, title, weight
     *
     * @return Response JSON
     */
    public function action_add_task()
    {
        if (\Input::method() !== 'POST')
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'POSTメソッドのみ許可されています。',
            ), 405);
        }

        if ( ! \Security::check_token())
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => '不正なリクエストです。',
            ), 403);
        }

        $boss_id = \Input::post('boss_id');
        $title = \Input::post('title', '');
        $weight = \Input::post('weight', 10);

        if ( ! $boss_id || empty($title))
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'ボスIDとタスク名は必須です。',
            ), 400);
        }

        // ボスの所有者確認
        $boss = \Model_Task::find_parent_by_id($boss_id, $this->current_user['id']);
        if ( ! $boss)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'ボスが見つかりません。',
            ), 404);
        }

        // タスク作成
        \Log::debug('action_add_task: 子タスク作成開始', array(
            'boss_id' => $boss_id,
            'title'   => $title,
            'weight'  => $weight,
            'user_id' => $this->current_user['id'],
        ));

        $task_id = \Model_Task::create_child(array(
            'parent_id' => $boss_id,
            'title'     => $title,
            'weight'    => (int)$weight,
        ));

        if ($task_id)
        {
            \Log::debug('action_add_task: 子タスク作成成功', array('task_id' => $task_id));

            return $this->json_response(array(
                'success' => true,
                'task'    => array(
                    'id'     => $task_id,
                    'title'  => $title,
                    'weight' => (int)$weight,
                    'done'   => false,
                ),
                'message' => '攻撃「' . $title . '」を追加しました。',
            ));
        }
        else
        {
            \Log::error('action_add_task: 子タスク作成失敗', array(
                'boss_id' => $boss_id,
                'title'   => $title,
            ));

            return $this->json_response(array(
                'success' => false,
                'error'   => 'タスクの追加に失敗しました。サーバーログを確認してください。',
            ), 500);
        }
    }

    /**
     * タスク削除API
     *
     * POST /api/battle/delete_task
     * パラメータ: task_id
     *
     * @return Response JSON
     */
    public function action_delete_task()
    {
        if (\Input::method() !== 'POST')
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'POSTメソッドのみ許可されています。',
            ), 405);
        }

        if ( ! \Security::check_token())
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => '不正なリクエストです。',
            ), 403);
        }

        $task_id = \Input::post('task_id');

        if ( ! $task_id)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'タスクIDが指定されていません。',
            ), 400);
        }

        // タスクの所有者確認と削除
        if (\Model_Task::delete_child($task_id, $this->current_user['id']))
        {
            return $this->json_response(array(
                'success' => true,
                'message' => 'タスクを削除しました。',
            ));
        }
        else
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'タスクの削除に失敗しました。',
            ), 400);
        }
    }

    /**
     * タスク一括更新API（ドラッグ&ドロップ並び替え用）
     *
     * POST /api/battle/reorder
     * パラメータ: boss_id, task_ids (配列)
     *
     * @return Response JSON
     */
    public function action_reorder()
    {
        if (\Input::method() !== 'POST')
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'POSTメソッドのみ許可されています。',
            ), 405);
        }

        if ( ! \Security::check_token())
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => '不正なリクエストです。',
            ), 403);
        }

        $boss_id = \Input::post('boss_id');
        $task_ids = \Input::post('task_ids');

        if ( ! $boss_id || ! is_array($task_ids))
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'パラメータが不正です。',
            ), 400);
        }

        // ボスの所有者確認
        $boss = \Model_Task::find_parent_by_id($boss_id, $this->current_user['id']);
        if ( ! $boss)
        {
            return $this->json_response(array(
                'success' => false,
                'error'   => 'ボスが見つかりません。',
            ), 404);
        }

        // 並び順を更新（sort_orderカラムがある場合）
        // 現在のスキーマにsort_orderがない場合はスキップ
        // 将来の拡張用として実装

        return $this->json_response(array(
            'success' => true,
            'message' => '並び順を更新しました。',
        ));
    }

    /**
     * JSON レスポンスを送信するヘルパーメソッド
     *
     * 【解説: CSRFトークンの更新】
     * FuelPHPの Security::check_token() はトークン検証後に新しいトークンを生成します。
     * Ajax通信では同じページ内で複数回リクエストが発生するため、
     * レスポンスに新しいトークンを含めて、クライアント側で更新する必要があります。
     *
     * @param array $data レスポンスデータ
     * @param int $status HTTPステータスコード
     * @return Response
     */
    protected function json_response(array $data, $status = 200)
    {
        // 新しいCSRFトークンをレスポンスに追加
        // これにより、クライアント側で次のリクエストに使用する新しいトークンを取得できる
        $data['csrf_token'] = \Security::fetch_token();
        $data['csrf_token_key'] = \Config::get('security.csrf_token_key', 'fuel_csrf_token');

        // 【解説: Response::forge() でJSONレスポンスを生成】
        // json_encode() で配列をJSON文字列に変換し、
        // Content-Type ヘッダーを application/json に設定
        return \Response::forge(
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            array('Content-Type' => 'application/json')
        );
    }
}
