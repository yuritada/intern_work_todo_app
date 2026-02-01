<?php
/**
 * Quest-Logic バトルロジッククラス
 *
 * 【解説: 独自名前空間の使用】
 * FuelPHPでは classes/ 以下のファイルは自動的にオートロードされます。
 * namespace を宣言することで、コントローラーやモデルとは別の
 * 論理的なグループとしてクラスを整理できます。
 *
 * このクラスは `Logic\Battle` として参照され、
 * ファイルパスは `classes/logic/battle.php` に対応します。
 *
 * 【解説: ビジネスロジックの分離】
 * ダメージ計算やレベルアップ判定などのビジネスロジックを
 * コントローラーに直接書くと、コードが肥大化し保守性が低下します。
 * ロジッククラスに分離することで、テストしやすく再利用可能なコードになります。
 */
namespace Logic;

class Battle
{
    /**
     * タスク完了時のダメージ計算と適用
     *
     * 子タスクを完了状態にし、親タスク（ボス）のHPを減算します。
     * ボスを倒した場合は経験値を付与し、レベルアップ判定を行います。
     *
     * @param int $child_task_id 完了する子タスクのID
     * @param int $user_id 実行ユーザーのID
     * @return array 処理結果 ('success', 'damage', 'new_hp', 'is_dead', 'gained_xp', 'level_up', 'new_level')
     */
    public static function calculate_damage($child_task_id, $user_id)
    {
        // 結果配列の初期化
        $result = array(
            'success'   => false,
            'damage'    => 0,
            'new_hp'    => 0,
            'is_dead'   => false,
            'gained_xp' => 0,
            'level_up'  => false,
            'new_level' => 0,
            'error'     => null,
        );

        // 【解説: トランザクションの使用】
        // 複数のテーブルを更新する処理では、途中で失敗した場合に
        // データの整合性が崩れないよう、トランザクションを使用します。
        // start_transaction() で開始し、成功時は commit()、失敗時は rollback() を呼びます。
        \DB::start_transaction();

        try
        {
            // 子タスクを取得（親タスク情報も含む）
            $child_task = \DB::select(
                    'child_tasks.id',
                    'child_tasks.parent_id',
                    'child_tasks.title',
                    'child_tasks.weight',
                    'child_tasks.done',
                    array('parent_tasks.current_hp', 'boss_current_hp'),
                    array('parent_tasks.boss_hp', 'boss_max_hp'),
                    array('parent_tasks.boss_rank', 'boss_rank'),
                    array('parent_tasks.project_id', 'project_id')
                )
                ->from('child_tasks')
                ->join('parent_tasks', 'INNER')
                ->on('child_tasks.parent_id', '=', 'parent_tasks.id')
                ->where('child_tasks.id', '=', $child_task_id)
                ->where('child_tasks.deleted_at', 'IS', \DB::expr('NULL'))
                ->execute()
                ->current();

            if ( ! $child_task)
            {
                throw new \Exception('タスクが見つかりません。');
            }

            // 既に完了済みのタスクはスキップ
            if ($child_task['done'] == 1)
            {
                $result['success'] = true;
                $result['new_hp'] = $child_task['boss_current_hp'];
                $result['is_dead'] = ($child_task['boss_current_hp'] <= 0);
                \DB::commit_transaction();
                return $result;
            }

            // プロジェクトの所有者確認
            $project = \DB::select('user_id')
                ->from('projects')
                ->where('id', '=', $child_task['project_id'])
                ->where('deleted_at', 'IS', \DB::expr('NULL'))
                ->execute()
                ->current();

            if ( ! $project || $project['user_id'] != $user_id)
            {
                throw new \Exception('このタスクを操作する権限がありません。');
            }

            // ダメージ計算（タスクの重み = ダメージ量）
            $damage = (int)$child_task['weight'];
            $new_hp = max(0, (int)$child_task['boss_current_hp'] - $damage);
            $is_dead = ($new_hp <= 0);

            // 子タスクを完了状態に更新
            \DB::update('child_tasks')
                ->set(array(
                    'done'       => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ))
                ->where('id', '=', $child_task_id)
                ->execute();

            // 親タスク（ボス）のHPを更新
            \DB::update('parent_tasks')
                ->set(array(
                    'current_hp' => $new_hp,
                    'updated_at' => date('Y-m-d H:i:s'),
                ))
                ->where('id', '=', $child_task['parent_id'])
                ->execute();

            $result['damage'] = $damage;
            $result['new_hp'] = $new_hp;
            $result['is_dead'] = $is_dead;

            // ボスを倒した場合、経験値を付与
            if ($is_dead)
            {
                // ボスを討伐済みにする
                \DB::update('parent_tasks')
                    ->set(array(
                        'done'       => 1,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ))
                    ->where('id', '=', $child_task['parent_id'])
                    ->execute();

                // 経験値を計算（設定ファイルから取得）
                \Config::load('quest', true);
                $boss_ranks = \Config::get('quest.boss_ranks');
                $rank = (int)$child_task['boss_rank'];
                $xp_reward = isset($boss_ranks[$rank]['xp_reward']) ? $boss_ranks[$rank]['xp_reward'] : 10;

                // ユーザーに経験値を付与
                \Model_User::add_xp($user_id, $xp_reward);
                $result['gained_xp'] = $xp_reward;

                // レベルアップ判定
                $level_result = static::check_level_up($user_id);
                $result['level_up'] = $level_result['level_up'];
                $result['new_level'] = $level_result['new_level'];
            }

            $result['success'] = true;
            \DB::commit_transaction();

        }
        catch (\Exception $e)
        {
            \DB::rollback_transaction();
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * レベルアップ判定と処理
     *
     * ユーザーの現在XPを確認し、レベルアップ条件を満たしていれば
     * レベルを上げます。複数レベル分の経験値がある場合も対応します。
     *
     * @param int $user_id ユーザーID
     * @return array 処理結果 ('level_up', 'new_level', 'levels_gained')
     */
    public static function check_level_up($user_id)
    {
        $result = array(
            'level_up'     => false,
            'new_level'    => 0,
            'levels_gained' => 0,
        );

        // ユーザー情報を取得
        $user = \Model_User::find_by_id($user_id);
        if ( ! $user)
        {
            return $result;
        }

        // 設定ファイルから経験値テーブルを取得
        \Config::load('quest', true);
        $xp_table = \Config::get('quest.xp_table');
        $max_level = \Config::get('quest.max_level', 20);

        $current_level = (int)$user['level'];
        $current_xp = (int)$user['xp'];
        $new_level = $current_level;

        // 【解説: 複数レベルアップの対応】
        // 一度に大量の経験値を獲得した場合、複数レベル上がる可能性があるため
        // ループで判定を繰り返します。
        while ($new_level < $max_level)
        {
            $next_level = $new_level + 1;
            $required_xp = isset($xp_table[$next_level]) ? $xp_table[$next_level] : PHP_INT_MAX;

            if ($current_xp >= $required_xp)
            {
                $new_level = $next_level;
            }
            else
            {
                break;
            }
        }

        // レベルが上がった場合、DBを更新
        if ($new_level > $current_level)
        {
            \Model_User::set_level($user_id, $new_level);
            $result['level_up'] = true;
            $result['new_level'] = $new_level;
            $result['levels_gained'] = $new_level - $current_level;
        }
        else
        {
            $result['new_level'] = $current_level;
        }

        return $result;
    }

    /**
     * タスクの完了を取り消す（アンドゥ機能）
     *
     * @param int $child_task_id 取り消す子タスクのID
     * @param int $user_id 実行ユーザーのID
     * @return array 処理結果
     */
    public static function undo_task($child_task_id, $user_id)
    {
        $result = array(
            'success' => false,
            'new_hp'  => 0,
            'error'   => null,
        );

        \DB::start_transaction();

        try
        {
            // 子タスクを取得
            $child_task = \DB::select(
                    'child_tasks.id',
                    'child_tasks.parent_id',
                    'child_tasks.weight',
                    'child_tasks.done',
                    array('parent_tasks.current_hp', 'boss_current_hp'),
                    array('parent_tasks.boss_hp', 'boss_max_hp'),
                    array('parent_tasks.project_id', 'project_id')
                )
                ->from('child_tasks')
                ->join('parent_tasks', 'INNER')
                ->on('child_tasks.parent_id', '=', 'parent_tasks.id')
                ->where('child_tasks.id', '=', $child_task_id)
                ->where('child_tasks.deleted_at', 'IS', \DB::expr('NULL'))
                ->execute()
                ->current();

            if ( ! $child_task)
            {
                throw new \Exception('タスクが見つかりません。');
            }

            // 未完了のタスクは取り消し不可
            if ($child_task['done'] == 0)
            {
                $result['success'] = true;
                $result['new_hp'] = $child_task['boss_current_hp'];
                \DB::commit_transaction();
                return $result;
            }

            // 所有者確認
            $project = \DB::select('user_id')
                ->from('projects')
                ->where('id', '=', $child_task['project_id'])
                ->execute()
                ->current();

            if ( ! $project || $project['user_id'] != $user_id)
            {
                throw new \Exception('このタスクを操作する権限がありません。');
            }

            // HPを復元（最大HPを超えないように）
            $restored_hp = min(
                (int)$child_task['boss_max_hp'],
                (int)$child_task['boss_current_hp'] + (int)$child_task['weight']
            );

            // 子タスクを未完了に戻す
            \DB::update('child_tasks')
                ->set(array(
                    'done'       => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ))
                ->where('id', '=', $child_task_id)
                ->execute();

            // ボスのHPを復元
            \DB::update('parent_tasks')
                ->set(array(
                    'current_hp' => $restored_hp,
                    'done'       => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ))
                ->where('id', '=', $child_task['parent_id'])
                ->execute();

            $result['success'] = true;
            $result['new_hp'] = $restored_hp;
            \DB::commit_transaction();

        }
        catch (\Exception $e)
        {
            \DB::rollback_transaction();
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
