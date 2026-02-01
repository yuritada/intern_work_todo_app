<?php
/**
 * Quest-Logic プロジェクトモデル (Model_Project)
 *
 * プロジェクト（冒険）のCRUD操作を担当します。
 * 論理削除と連鎖削除（関連する親タスク・子タスクも削除）をサポートします。
 */
class Model_Project
{
    const TABLE_NAME = 'projects';

    /**
     * ユーザーのプロジェクト一覧を取得
     *
     * @param int $user_id ユーザーID
     * @param bool $include_deleted 論理削除済みも含めるか
     * @return array プロジェクト一覧
     */
    public static function find_by_user($user_id, $include_deleted = false)
    {
        $query = \DB::select()
            ->from(static::TABLE_NAME)
            ->where('user_id', '=', $user_id);

        if ( ! $include_deleted)
        {
            $query->where('deleted_at', 'IS', \DB::expr('NULL'));
        }

        $query->order_by('created_at', 'desc');

        return $query->execute()->as_array();
    }

    /**
     * プロジェクトをIDで取得
     *
     * @param int $id プロジェクトID
     * @param int|null $user_id ユーザーID（指定時は所有者確認）
     * @return array|null プロジェクト情報
     */
    public static function find_by_id($id, $user_id = null)
    {
        $query = \DB::select()
            ->from(static::TABLE_NAME)
            ->where('id', '=', $id)
            ->where('deleted_at', 'IS', \DB::expr('NULL'));

        if ($user_id !== null)
        {
            $query->where('user_id', '=', $user_id);
        }

        return $query->execute()->current();
    }

    /**
     * プロジェクトを作成
     *
     * @param array $data プロジェクトデータ ('user_id', 'title', 'deadline', 'memo')
     * @return int|false 作成されたプロジェクトID、失敗時はfalse
     */
    public static function create(array $data)
    {
        if (empty($data['user_id']) || empty($data['title']))
        {
            return false;
        }

        $insert_data = array(
            'user_id'    => (int)$data['user_id'],
            'title'      => $data['title'],
            'deadline'   => isset($data['deadline']) && $data['deadline'] ? $data['deadline'] : null,
            'memo'       => isset($data['memo']) ? $data['memo'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $result = \DB::insert(static::TABLE_NAME)
            ->set($insert_data)
            ->execute();

        return isset($result[0]) ? $result[0] : false;
    }

    /**
     * プロジェクトを更新
     *
     * @param int $id プロジェクトID
     * @param array $data 更新データ
     * @param int $user_id 所有者確認用ユーザーID
     * @return bool 成功時true
     */
    public static function update($id, array $data, $user_id)
    {
        $allowed = array('title', 'deadline', 'memo');
        $update_data = array();

        foreach ($allowed as $key)
        {
            if (isset($data[$key]))
            {
                $update_data[$key] = $data[$key];
            }
        }

        if (empty($update_data))
        {
            return false;
        }

        $update_data['updated_at'] = date('Y-m-d H:i:s');

        $affected = \DB::update(static::TABLE_NAME)
            ->set($update_data)
            ->where('id', '=', $id)
            ->where('user_id', '=', $user_id)
            ->where('deleted_at', 'IS', \DB::expr('NULL'))
            ->execute();

        return $affected > 0;
    }

    /**
     * プロジェクトを論理削除（連鎖削除含む）
     *
     * 【解説: 論理削除と連鎖削除】
     * 物理削除（DELETE）ではなく、deleted_at に日時を設定する論理削除を行います。
     * これにより、誤削除時のデータ復旧や、削除履歴の追跡が可能になります。
     * また、プロジェクトに紐づく親タスク・子タスクも同時に論理削除します。
     *
     * @param int $id プロジェクトID
     * @param int $user_id 所有者確認用ユーザーID
     * @return bool 成功時true
     */
    public static function delete($id, $user_id)
    {
        // 【解説: トランザクションによるデータ整合性の保証】
        // 複数テーブルを更新するため、途中で失敗した場合に
        // 全ての変更をロールバックできるようトランザクションを使用
        \DB::start_transaction();

        try
        {
            $now = date('Y-m-d H:i:s');

            // プロジェクトを論理削除
            $affected = \DB::update(static::TABLE_NAME)
                ->set(array(
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ))
                ->where('id', '=', $id)
                ->where('user_id', '=', $user_id)
                ->where('deleted_at', 'IS', \DB::expr('NULL'))
                ->execute();

            if ($affected == 0)
            {
                throw new \Exception('プロジェクトが見つからないか、既に削除されています。');
            }

            // 紐づく親タスクのIDを取得
            $parent_ids = \DB::select('id')
                ->from('parent_tasks')
                ->where('project_id', '=', $id)
                ->where('deleted_at', 'IS', \DB::expr('NULL'))
                ->execute()
                ->as_array('id');

            if ( ! empty($parent_ids))
            {
                $parent_id_list = array_keys($parent_ids);

                // 親タスクを論理削除
                \DB::update('parent_tasks')
                    ->set(array(
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ))
                    ->where('id', 'IN', $parent_id_list)
                    ->execute();

                // 子タスクを論理削除
                \DB::update('child_tasks')
                    ->set(array(
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ))
                    ->where('parent_id', 'IN', $parent_id_list)
                    ->where('deleted_at', 'IS', \DB::expr('NULL'))
                    ->execute();
            }

            \DB::commit_transaction();
            return true;

        }
        catch (\Exception $e)
        {
            \DB::rollback_transaction();
            return false;
        }
    }

    /**
     * プロジェクトの進捗情報を取得
     *
     * @param int $id プロジェクトID
     * @return array 進捗情報 ('total_bosses', 'defeated_bosses', 'total_tasks', 'completed_tasks', 'progress_percent')
     */
    public static function get_progress($id)
    {
        // ボス（親タスク）の統計
        $boss_stats = \DB::select(
                \DB::expr('COUNT(*) as total'),
                \DB::expr('SUM(CASE WHEN done = 1 THEN 1 ELSE 0 END) as defeated')
            )
            ->from('parent_tasks')
            ->where('project_id', '=', $id)
            ->where('deleted_at', 'IS', \DB::expr('NULL'))
            ->execute()
            ->current();

        // 子タスクの統計
        $task_stats = \DB::select(
                \DB::expr('COUNT(*) as total'),
                \DB::expr('SUM(CASE WHEN child_tasks.done = 1 THEN 1 ELSE 0 END) as completed')
            )
            ->from('child_tasks')
            ->join('parent_tasks', 'INNER')
            ->on('child_tasks.parent_id', '=', 'parent_tasks.id')
            ->where('parent_tasks.project_id', '=', $id)
            ->where('child_tasks.deleted_at', 'IS', \DB::expr('NULL'))
            ->where('parent_tasks.deleted_at', 'IS', \DB::expr('NULL'))
            ->execute()
            ->current();

        $total_tasks = (int)$task_stats['total'];
        $completed_tasks = (int)$task_stats['completed'];
        $progress_percent = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

        return array(
            'total_bosses'    => (int)$boss_stats['total'],
            'defeated_bosses' => (int)$boss_stats['defeated'],
            'total_tasks'     => $total_tasks,
            'completed_tasks' => $completed_tasks,
            'progress_percent' => $progress_percent,
        );
    }
}
