<?php
/**
 * Quest-Logic 共通コントローラー (Controller_Base)
 *
 * 【解説: なぜ名前空間を使わないのか】
 * FuelPHPのコントローラーはアンダースコア規則（PEAR命名規則）に従います。
 * クラス名 Controller_Base は fuel/app/classes/controller/base.php に対応し、
 * オートローダーがこの規則でクラスを自動的に読み込みます。
 * 独自の namespace を使うと FuelPHP のルーティングが正常に動作しなくなります。
 *
 * 【解説: グローバル名前空間からの呼び出し（バックスラッシュ）】
 * FuelPHPのコアクラス（Config, Session, Response, DB 等）はグローバル名前空間に
 * 存在します。PHPでは名前空間内から他の名前空間のクラスを呼び出す際、
 * 完全修飾名（バックスラッシュ付き）を使用する必要があります。
 * ただし、このファイルは名前空間を宣言していないため、
 * バックスラッシュは省略可能ですが、明示的に記述することで
 * 「グローバルクラスを使用している」ことを明確にしています。
 */

/**
 * 全コントローラーの基底クラス
 *
 * Controller_Template を継承し、before() メソッドで以下の共通処理を行う：
 * - ゲーム設定（quest.php）の読み込み
 * - セッション確認と認証チェック
 * - ログインユーザー情報の取得とViewへの共有
 */
class Controller_Base extends \Controller_Template
{
    /**
     * テンプレートファイルのパス
     * views/template.php を共通レイアウトとして使用
     *
     * @var string
     */
    public $template = 'template';

    /**
     * 現在ログイン中のユーザー情報を保持
     * before() で取得し、各アクションで利用可能
     *
     * @var array|null
     */
    protected $current_user = null;

    /**
     * ゲーム設定（quest.php）を保持
     *
     * @var array
     */
    protected $quest_config = array();

    /**
     * 認証が不要なアクションを定義
     * サブクラスでオーバーライドして認証スキップ対象を指定可能
     *
     * @var array
     */
    protected $no_auth_actions = array();

    /**
     * 各アクション実行前に呼ばれるメソッド
     *
     * 【解説: before() メソッドの役割】
     * FuelPHPでは before() はコントローラーアクションの実行前に必ず呼ばれます。
     * ここで認証チェックや共通データの取得を行うことで、
     * 各アクションで重複したコードを書く必要がなくなります。
     *
     * @return void
     */
    public function before()
    {
        // 親クラス（Controller_Template）の before() を必ず呼ぶ
        // これにより $this->template が View オブジェクトとして初期化される
        parent::before();

        // ゲーム設定ファイル（quest.php）を読み込み
        // 第2引数 true でグループ名 'quest' としてアクセス可能にする
        // 例: \Config::get('quest.boss_ranks')
        \Config::load('quest', true);
        // 【Null安全化】Config::get() が null を返す場合に備え、空配列をデフォルトに
        $this->quest_config = \Config::get('quest') ?? array();

        // 現在のアクション名を取得
        $current_action = \Request::active()->action;

        // 認証不要アクションかどうかを判定
        $skip_auth = in_array($current_action, $this->no_auth_actions);

        // セッションからユーザーIDを取得
        // Session::get() は該当キーが存在しない場合 null を返す
        $user_id = \Session::get('user_id');

        if ($user_id)
        {
            // 【解説: DB クラスの使用（ORM禁止のため）】
            // FuelPHP の DB クラス（クエリビルダ）を使用してSQLを発行
            // \DB::select() でSELECT文を構築し、execute() で実行
            // current() で結果セットの最初の行を連想配列で取得
            $this->current_user = \DB::select('id', 'username', 'level', 'xp')
                ->from('users')
                ->where('id', '=', $user_id)
                ->execute()
                ->current();

            // ユーザーが見つからない場合（削除された等）はセッションをクリア
            if ( ! $this->current_user)
            {
                \Session::delete('user_id');
            }
        }

        // 認証チェック: 未ログインかつ認証が必要なアクションの場合
        if ( ! $this->current_user && ! $skip_auth)
        {
            // ログイン画面へリダイレクト
            // Response::redirect() は内部で exit() を呼ぶため、以降の処理は実行されない
            \Response::redirect('auth/login');
        }

        // 【解説: View への共通変数セット】
        // set_global() を使用すると、このリクエスト内の全ての View から
        // 指定した変数にアクセスできるようになる（ヘッダーでユーザー名表示等に利用）
        if ($this->template instanceof \View)
        {
            // ユーザー情報をテンプレートで利用可能にする
            $this->template->set_global('current_user', $this->current_user);

            // ゲーム設定もテンプレートで利用可能にする（レベルアップ表示等に使用）
            $this->template->set_global('quest_config', $this->quest_config);

            // ユーザーがログイン中の場合、追加情報を計算してセット
            if ($this->current_user)
            {
                // 次のレベルに必要なXPを計算
                $next_level_xp = $this->get_next_level_xp($this->current_user['level']);
                $this->template->set_global('next_level_xp', $next_level_xp);

                // 現在レベルでの進捗率を計算（プログレスバー表示用）
                $current_level_xp = $this->get_current_level_xp($this->current_user['level']);
                $xp_progress = 0;
                if ($next_level_xp > $current_level_xp)
                {
                    $xp_progress = ($this->current_user['xp'] - $current_level_xp)
                                 / ($next_level_xp - $current_level_xp) * 100;
                }
                $this->template->set_global('xp_progress', $xp_progress);
            }
        }
    }

    /**
     * 指定レベルに到達するために必要な累計XPを取得
     *
     * @param int $level 対象レベル
     * @return int 必要な累計XP
     */
    protected function get_current_level_xp($level)
    {
        // 【Null安全化】xp_table キーが存在しない場合に備え、空配列をデフォルトに
        $xp_table = $this->quest_config['xp_table'] ?? array();
        return isset($xp_table[$level]) ? $xp_table[$level] : 0;
    }

    /**
     * 次のレベルに必要な累計XPを取得
     *
     * @param int $current_level 現在のレベル
     * @return int 次のレベルに必要な累計XP（最大レベルの場合は現在レベルのXPを返す）
     */
    protected function get_next_level_xp($current_level)
    {
        // 【Null安全化】xp_table キーが存在しない場合に備え、空配列をデフォルトに
        $xp_table = $this->quest_config['xp_table'] ?? array();
        $next_level = $current_level + 1;

        if (isset($xp_table[$next_level]))
        {
            return $xp_table[$next_level];
        }

        // 最大レベルに達している場合
        return isset($xp_table[$current_level]) ? $xp_table[$current_level] : 0;
    }
}
