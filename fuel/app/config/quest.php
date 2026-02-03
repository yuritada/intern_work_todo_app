<?php
/**
 * Quest-Logic ゲームバランス設定ファイル
 *
 * 【解説】
 * FuelPHPでは config/ ディレクトリに配列を返すPHPファイルを配置することで、
 * Config::load('quest') でアプリケーション全体から設定値にアクセスできます。
 * ゲームバランス（HP倍率、経験値など）を外部ファイルで管理することで、
 * コードを変更せずに調整が可能になります。
 */

return array(

    /**
     * ボスランク定義
     *
     * ランク1〜5まで定義し、それぞれに以下を設定：
     * - label: 表示名（UI用）
     * - hp_multiplier: HP倍率（基本HP × この倍率 = 最大HP）
     * - xp_reward: 討伐時に獲得できる経験値
     */
    'boss_ranks' => array(
        1 => array(
            'label'         => '係長級',
            'hp_multiplier' => 1.0,
            'xp_reward'     => 10,
        ),
        2 => array(
            'label'         => '課長級',
            'hp_multiplier' => 2.0,
            'xp_reward'     => 30,
        ),
        3 => array(
            'label'         => '部長級',
            'hp_multiplier' => 3.5,
            'xp_reward'     => 80,
        ),
        4 => array(
            'label'         => '役員級',
            'hp_multiplier' => 5.0,
            'xp_reward'     => 200,
        ),
        5 => array(
            'label'         => '社長級',
            'hp_multiplier' => 8.0,
            'xp_reward'     => 500,
        ),
    ),

    /**
     * 経験値テーブル（レベルアップに必要な累計XP）
     *
     * 【解説】
     * 配列のキーがレベル、値がそのレベルに到達するために必要な累計経験値。
     * レベル1は初期状態なのでXP 0、レベル2になるにはXP 100が必要...という形式。
     * ユーザーの現在XPがこのテーブルの値以上であればレベルアップ判定を行う。
     */
    'xp_table' => array(
        1  => 0,
        2  => 100,
        3  => 300,
        4  => 600,
        5  => 1000,
        6  => 1500,
        7  => 2100,
        8  => 2800,
        9  => 3600,
        10 => 4500,
        11 => 5500,
        12 => 6600,
        13 => 7800,
        14 => 9100,
        15 => 10500,
        16 => 12000,
        17 => 13600,
        18 => 15300,
        19 => 17100,
        20 => 19000,
    ),

    /**
     * 最大レベル
     */
    'max_level' => 20,

    /**
     * 子タスク（攻撃）のデフォルト重み（ダメージ量）
     *
     * 【解説】
     * 子タスクを完了した際に、ボスに与えるダメージの基本値。
     * 各タスクで個別に weight を設定可能だが、未設定時はこの値が使用される。
     */
    'default_task_weight' => 10,

    /**
     * 心理的重み（Psychological Weights）
     *
     * 【解説: Phase 5 - UX向上機能】
     * ユーザーが数値を入力する代わりに「簡単/普通/きつい」から選ぶことで、
     * 適切な攻撃力（weight）が自動的に設定されます。
     * これにより、ゲームバランスの破綻を防ぎ、ユーザーの心理的負担を軽減します。
     *
     * - key: 選択肢の識別子（フォームのvalue値として使用）
     * - label: UIに表示するラベル
     * - weight: 実際にDBに保存されるダメージ値
     * - description: ユーザーへの説明文
     */
    'psychological_weights' => array(
        'easy' => array(
            'label'       => '簡単',
            'weight'      => 5,
            'description' => 'すぐに終わる軽いタスク',
        ),
        'normal' => array(
            'label'       => '普通',
            'weight'      => 15,
            'description' => '通常の作業量のタスク',
        ),
        'hard' => array(
            'label'       => 'きつい',
            'weight'      => 35,
            'description' => '時間がかかる重いタスク',
        ),
    ),

    /**
     * ボスの基本HP
     *
     * 【解説】
     * この値に boss_rank の hp_multiplier を掛けて最大HPを算出する。
     * 例: 基本HP 100 × 係長級(1.0) = 100HP
     *     基本HP 100 × 社長級(8.0) = 800HP
     */
    'base_boss_hp' => 100,

    /**
     * HPバーの色設定（残りHP割合に応じた色）
     *
     * 【解説】
     * フロントエンド（Knockout.js）でHPバーの色を動的に変更する際に使用。
     * threshold: この割合以上の時に適用, color: CSSカラーコード
     */
    'hp_bar_colors' => array(
        array('threshold' => 0.5, 'color' => '#4CAF50'),  // 50%以上: 緑
        array('threshold' => 0.2, 'color' => '#FFC107'),  // 20%以上: 黄
        array('threshold' => 0.0, 'color' => '#F44336'),  // それ以下: 赤
    ),
);
