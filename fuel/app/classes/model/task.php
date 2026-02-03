<?php
/**
 * Quest-Logic タスクモデル (Model_Task)
 *
 * 親タスク（ボス/ミッション）と子タスク（攻撃/アクション）の
 * CRUD操作を担当します。
 *
 * 【解説: 1:n リレーションの取得】
 * プロジェクト -> 親タスク -> 子タスク の階層構造があります。
 * JOINを使用した効率的なクエリで関連データを取得します。
 */
class Model_Task
{
    /**
     * 配列内のnull値を空文字列に変換（FuelPHP auto_filter との互換性のため）
     *
     * 【解説: PHP 7.3 互換性問題】
     * FuelPHP の Security::htmlentities() は null を受け取ると
     * get_class(null) でエラーが発生します。
     * この関数でnull値を空文字列に変換することで問題を回避します。
     *
     * @param mixed $data 変換対象のデータ
     * @return mixed null が空文字列に変換されたデータ
     */
    protected static function sanitize_null($data)
    {
        if (is_array($data))
        {
            foreach ($data as $key => $value)
            {
                $data[$key] = static::sanitize_null($value);
            }
            return $data;
        }
        return $data === null ? '' : $data;
    }

    /**
     * 親タスク（ボス）をIDで取得
     *
     * @param int $id 親タスクID
     * @param int|null $user_id 所有者確認用（プロジェクト経由）
     * @return array|null 親タスク情報
     */
    public static function find_parent_by_id($id, $user_id = null)
    {
        $query = \DB::select(
                'parent_tasks.*',
                array('projects.user_id', 'owner_id'),
                array('projects.title', 'project_title')
            )
            ->from('parent_tasks')
            ->join('projects', 'INNER')
            ->on('parent_tasks.project_id', '=', 'projects.id')
            ->where('parent_tasks.id', '=', $id)
            ->where('parent_tasks.deleted_at', 'IS', \DB::expr('NULL'))
            ->where('projects.deleted_at', 'IS', \DB::expr('NULL'));

        if ($user_id !== null)
        {
            $query->where('projects.user_id', '=', $user_id);
        }

        $result = $query->execute()->current();
        return $result ? static::sanitize_null($result) : null;
    }

    /**
     * プロジェクトの親タスク（ボス）一覧を取得
     *
     * @param int $project_id プロジェクトID
     * @return array 親タスク一覧
     */
    public static function find_parents_by_project($project_id)
    {
        $results = \DB::select()
            ->from('parent_tasks')
            ->where('project_id', '=', $project_id)
            ->where('deleted_at', 'IS', \DB::expr('NULL'))
            ->order_by('created_at', 'asc')
            ->execute()
            ->as_array();

        return static::sanitize_null($results);
    }

    /**
     * 親タスク（ボス）を作成
     *
     * @param array $data ボスデータ ('project_id', 'title', 'boss_rank', 'deadline')
     * @return int|false 作成された親タスクID
     */
    public static function create_parent(array $data)
    {
        if (empty($data['project_id']) || empty($data['title']))
        {
            return false;
        }

        // ボスランクに応じたHP計算
        \Config::load('quest', true);
        $boss_ranks = \Config::get('quest.boss_ranks');
        $base_hp = \Config::get('quest.base_boss_hp', 100);

        $rank = isset($data['boss_rank']) ? (int)$data['boss_rank'] : 1;
        $rank = max(1, min(5, $rank)); // 1-5の範囲に制限

        $multiplier = isset($boss_ranks[$rank]['hp_multiplier']) ? $boss_ranks[$rank]['hp_multiplier'] : 1.0;
        $boss_hp = (int)($base_hp * $multiplier);

        $insert_data = array(
            'project_id' => (int)$data['project_id'],
            'title'      => $data['title'],
            'boss_rank'  => $rank,
            'boss_hp'    => $boss_hp,
            'current_hp' => $boss_hp,
            'done'       => 0,
            // DATE型カラムにはNULLを使用（空文字列はMySQLエラーになる）
            // View表示時は sanitize_null() で空文字列に変換される
            'deadline'   => isset($data['deadline']) && $data['deadline'] ? $data['deadline'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $result = \DB::insert('parent_tasks')
            ->set($insert_data)
            ->execute();

        return isset($result[0]) ? $result[0] : false;
    }

    /**
     * 親タスク（ボス）を更新
     *
     * @param int $id 親タスクID
     * @param array $data 更新データ
     * @param int $user_id 所有者確認用
     * @return bool 成功時true
     */
    public static function update_parent($id, array $data, $user_id)
    {
        // 所有者確認
        $parent = static::find_parent_by_id($id, $user_id);
        if ( ! $parent)
        {
            return false;
        }

        $allowed = array('title', 'boss_rank', 'deadline');
        $update_data = array();

        foreach ($allowed as $key)
        {
            if (isset($data[$key]))
            {
                $update_data[$key] = $data[$key];
            }
        }

        // ランクが変更された場合、HPも再計算
        if (isset($update_data['boss_rank']))
        {
            \Config::load('quest', true);
            $boss_ranks = \Config::get('quest.boss_ranks');
            $base_hp = \Config::get('quest.base_boss_hp', 100);

            $rank = max(1, min(5, (int)$update_data['boss_rank']));
            $multiplier = isset($boss_ranks[$rank]['hp_multiplier']) ? $boss_ranks[$rank]['hp_multiplier'] : 1.0;
            $new_boss_hp = (int)($base_hp * $multiplier);

            $update_data['boss_rank'] = $rank;
            $update_data['boss_hp'] = $new_boss_hp;
            // 現在HPも新しい最大HPに合わせる（比率を維持）
            if ($parent['boss_hp'] > 0)
            {
                $hp_ratio = $parent['current_hp'] / $parent['boss_hp'];
                $update_data['current_hp'] = (int)($new_boss_hp * $hp_ratio);
            }
        }

        if (empty($update_data))
        {
            return false;
        }

        $update_data['updated_at'] = date('Y-m-d H:i:s');

        $affected = \DB::update('parent_tasks')
            ->set($update_data)
            ->where('id', '=', $id)
            ->execute();

        return $affected > 0;
    }

    /**
     * 親タスク（ボス）を論理削除
     *
     * @param int $id 親タスクID
     * @param int $user_id 所有者確認用
     * @return bool 成功時true
     */
    public static function delete_parent($id, $user_id)
    {
        // 所有者確認
        $parent = static::find_parent_by_id($id, $user_id);
        if ( ! $parent)
        {
            return false;
        }

        \DB::start_transaction();

        try
        {
            $now = date('Y-m-d H:i:s');

            // 親タスクを論理削除
            \DB::update('parent_tasks')
                ->set(array(
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ))
                ->where('id', '=', $id)
                ->execute();

            // 紐づく子タスクも論理削除
            \DB::update('child_tasks')
                ->set(array(
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ))
                ->where('parent_id', '=', $id)
                ->where('deleted_at', 'IS', \DB::expr('NULL'))
                ->execute();

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
     * 親タスクの子タスク一覧を取得
     *
     * @param int $parent_id 親タスクID
     * @return array 子タスク一覧
     */
    public static function find_children_by_parent($parent_id)
    {
        $results = \DB::select()
            ->from('child_tasks')
            ->where('parent_id', '=', $parent_id)
            ->where('deleted_at', 'IS', \DB::expr('NULL'))
            ->order_by('created_at', 'asc')
            ->execute()
            ->as_array();

        return static::sanitize_null($results);
    }

    /**
     * 子タスクをIDで取得
     *
     * @param int $id 子タスクID
     * @param int|null $user_id 所有者確認用
     * @return array|null 子タスク情報
     */
    public static function find_child_by_id($id, $user_id = null)
    {
        $query = \DB::select(
                'child_tasks.*',
                array('parent_tasks.title', 'parent_title'),
                array('parent_tasks.project_id', 'project_id'),
                array('projects.user_id', 'owner_id')
            )
            ->from('child_tasks')
            ->join('parent_tasks', 'INNER')
            ->on('child_tasks.parent_id', '=', 'parent_tasks.id')
            ->join('projects', 'INNER')
            ->on('parent_tasks.project_id', '=', 'projects.id')
            ->where('child_tasks.id', '=', $id)
            ->where('child_tasks.deleted_at', 'IS', \DB::expr('NULL'));

        if ($user_id !== null)
        {
            $query->where('projects.user_id', '=', $user_id);
        }

        $result = $query->execute()->current();
        return $result ? static::sanitize_null($result) : null;
    }

    /**
     * 子タスク（攻撃アクション）を作成
     *
     * @param array $data 子タスクデータ ('parent_id', 'title', 'weight')
     * @return int|false 作成された子タスクID
     */
    public static function create_child(array $data)
    {
        if (empty($data['parent_id']) || empty($data['title']))
        {
            \Log::warning('create_child: parent_id または title が空です。', $data);
            return false;
        }

        \Config::load('quest', true);
        $default_weight = \Config::get('quest.default_task_weight', 10);

        $insert_data = array(
            'parent_id'  => (int)$data['parent_id'],
            'title'      => $data['title'],
            'weight'     => isset($data['weight']) ? (int)$data['weight'] : $default_weight,
            'done'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        try
        {
            $result = \DB::insert('child_tasks')
                ->set($insert_data)
                ->execute();

            return isset($result[0]) ? $result[0] : false;
        }
        catch (\Database_Exception $e)
        {
            \Log::error('create_child: DBエラー - ' . $e->getMessage(), $insert_data);
            return false;
        }
    }

    /**
     * 子タスクを更新
     *
     * @param int $id 子タスクID
     * @param array $data 更新データ
     * @param int $user_id 所有者確認用
     * @return bool 成功時true
     */
    public static function update_child($id, array $data, $user_id)
    {
        // 所有者確認
        $child = static::find_child_by_id($id, $user_id);
        if ( ! $child)
        {
            return false;
        }

        $allowed = array('title', 'weight');
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

        $affected = \DB::update('child_tasks')
            ->set($update_data)
            ->where('id', '=', $id)
            ->execute();

        return $affected > 0;
    }

    /**
     * 子タスクを論理削除
     *
     * @param int $id 子タスクID
     * @param int $user_id 所有者確認用
     * @return bool 成功時true
     */
    public static function delete_child($id, $user_id)
    {
        // 所有者確認
        $child = static::find_child_by_id($id, $user_id);
        if ( ! $child)
        {
            return false;
        }

        $affected = \DB::update('child_tasks')
            ->set(array(
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ))
            ->where('id', '=', $id)
            ->execute();

        return $affected > 0;
    }

    /**
     * ユーザーの遭遇中ボス（未討伐の親タスク）を取得
     *
     * 締切が近い順にソートして返します。
     *
     * @param int $user_id ユーザーID
     * @param int $limit 取得件数
     * @return array ボス一覧
     */
    public static function find_active_bosses($user_id, $limit = 5)
    {
        $results = \DB::select(
                'parent_tasks.*',
                array('projects.title', 'project_title'),
                array('projects.id', 'project_id')
            )
            ->from('parent_tasks')
            ->join('projects', 'INNER')
            ->on('parent_tasks.project_id', '=', 'projects.id')
            ->where('projects.user_id', '=', $user_id)
            ->where('projects.deleted_at', 'IS', \DB::expr('NULL'))
            ->where('parent_tasks.deleted_at', 'IS', \DB::expr('NULL'))
            ->where('parent_tasks.done', '=', 0)
            ->order_by(\DB::expr('CASE WHEN parent_tasks.deadline IS NULL THEN 1 ELSE 0 END'), 'asc')
            ->order_by('parent_tasks.deadline', 'asc')
            ->order_by('parent_tasks.created_at', 'asc')
            ->limit($limit)
            ->execute()
            ->as_array();

        return static::sanitize_null($results);
    }

    /**
     * 親タスクとその子タスクを一括取得（ミッション詳細用）
     *
     * 【解説: 効率的なデータ取得】
     * N+1問題を避けるため、親タスクと子タスクを別々のクエリで取得し、
     * PHP側で結合します。これによりDBへのアクセス回数を最小限に抑えます。
     *
     * @param int $parent_id 親タスクID
     * @param int $user_id 所有者確認用
     * @return array|null 親タスク情報（子タスク配列を含む）
     */
    public static function find_parent_with_children($parent_id, $user_id)
    {
        // 親タスクを取得
        $parent = static::find_parent_by_id($parent_id, $user_id);
        if ( ! $parent)
        {
            return null;
        }

        // 子タスクを取得
        $children = static::find_children_by_parent($parent_id);

        // 親タスクに子タスク配列を追加
        $parent['children'] = $children;

        // HP割合を計算
        $parent['hp_percent'] = $parent['boss_hp'] > 0
            ? round(($parent['current_hp'] / $parent['boss_hp']) * 100)
            : 0;

        // 完了タスク数を計算
        $completed = 0;
        foreach ($children as $child)
        {
            if ($child['done'] == 1)
            {
                $completed++;
            }
        }
        $parent['completed_tasks'] = $completed;
        // 【Null安全化】$childrenがnullの場合に備えたフォールバック
        $parent['total_tasks'] = is_array($children) ? count($children) : 0;

        return $parent;
    }
}
