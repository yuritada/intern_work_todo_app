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

        // 未認証の場合はJSONエラーを返す
        if ( ! $this->current_user)
        {
            $this->json_response(array(
                'success' => false,
                'error'   => '認証が必要です。',
            ), 401);
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
            return $this->json_response(array(
                'success'   => true,
                'damage'    => $result['damage'],
                'new_hp'    => $result['new_hp'],
                'is_dead'   => $result['is_dead'],
                'gained_xp' => $result['gained_xp'],
                'level_up'  => $result['level_up'],
                'new_level' => $result['new_level'],
            ));
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
     * JSON レスポンスを送信するヘルパーメソッド
     *
     * @param array $data レスポンスデータ
     * @param int $status HTTPステータスコード
     * @return Response
     */
    protected function json_response(array $data, $status = 200)
    {
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
