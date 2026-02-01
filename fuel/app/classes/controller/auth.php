<?php
/**
 * Quest-Logic 認証コントローラー (Controller_Auth)
 *
 * 【解説: なぜ Controller_Base を継承しないのか】
 * Controller_Base の before() メソッドでは認証チェックを行い、
 * 未ログイン時はログイン画面へリダイレクトします。
 * しかし、ログイン画面自体が認証を必要としてしまうと
 * 無限リダイレクトループが発生してしまいます。
 *
 * そのため、認証関連のコントローラーは Controller_Base を継承せず、
 * 直接 Controller_Template を継承します。
 */
class Controller_Auth extends \Controller_Template
{
    /**
     * テンプレートファイルのパス
     */
    public $template = 'template';

    /**
     * アクション実行前の処理
     */
    public function before()
    {
        parent::before();

        // 設定ファイルの読み込み（テンプレートで使用する可能性があるため）
        \Config::load('quest', true);

        // 【解説】認証コントローラーでは current_user は常に 空にする
        // ログイン前のユーザーがアクセスするため
        $this->template->set_global('current_user', '');
        $this->template->set_global('quest_config', \Config::get('quest'));
    }

    /**
     * ログイン画面表示・ログイン処理
     *
     * GET: ログインフォームを表示
     * POST: ログイン処理を実行
     */
    public function action_login()
    {
        // 既にログイン済みの場合はダッシュボードへリダイレクト
        if (\Session::get('user_id'))
        {
            \Response::redirect('dashboard');
        }

        $data = array(
            'error'    => '',
            'username' => '',
        );

        // 【解説: Input::method() でHTTPメソッドを判定】
        // RESTful な設計では、同一URLに対してGET/POSTで異なる処理を行う
        if (\Input::method() === 'POST')
        {
            // 【解説: Input::post() でPOSTデータを取得】
            // 第2引数はデフォルト値（該当キーがない場合に返す値）
            // FuelPHPのInputクラスは自動的にXSSフィルタリングを行う
            $username = \Input::post('username', '');
            $password = \Input::post('password', '');

            // CSRF トークン検証
            if ( ! \Security::check_token())
            {
                $data['error'] = '不正なリクエストです。もう一度お試しください。';
            }
            else
            {
                // Model_User のログイン処理を呼び出し
                $user = \Model_User::login($username, $password);

                if ($user)
                {
                    // 【解説: Session::set() でセッションにデータを保存】
                    // user_id をセッションに保存することでログイン状態を維持
                    // セッションはサーバー側で管理され、クライアントにはセッションIDのみが渡される
                    \Session::set('user_id', $user['id']);

                    // 【解説: Session::set_flash() で一時メッセージを保存】
                    // フラッシュデータは次のリクエストでのみ利用可能
                    // リダイレクト後にメッセージを表示するのに便利
                    \Session::set_flash('success', 'ログインしました。ようこそ、' . $user['username'] . 'さん！');

                    \Response::redirect('dashboard');
                }
                else
                {
                    $data['error'] = 'ユーザー名またはパスワードが正しくありません。';
                    $data['username'] = $username; // 入力値を保持
                }
            }
        }

        // ビューのタイトルを設定
        $this->template->title = 'Login';
        // ビューをレンダリングして content にセット
        $this->template->content = \View::forge('auth/login', $data);
    }

    /**
     * ユーザー登録画面表示・登録処理
     *
     * GET: 登録フォームを表示
     * POST: 登録処理を実行
     */
    public function action_register()
    {
        // 既にログイン済みの場合はダッシュボードへリダイレクト
        if (\Session::get('user_id'))
        {
            \Response::redirect('dashboard');
        }

        $data = array(
            'errors'   => array(),
            'username' => '',
        );

        if (\Input::method() === 'POST')
        {
            $username         = \Input::post('username', '');
            $password         = \Input::post('password', '');
            $password_confirm = \Input::post('password_confirm', '');

            // CSRF トークン検証
            if ( ! \Security::check_token())
            {
                $data['errors'][] = '不正なリクエストです。もう一度お試しください。';
            }
            else
            {
                // バリデーション
                $errors = static::validate_registration($username, $password, $password_confirm);

                if (empty($errors))
                {
                    // ユーザー登録実行
                    $user_id = \Model_User::register(array(
                        'username' => $username,
                        'password' => $password,
                    ));

                    if ($user_id)
                    {
                        // 登録成功: 自動ログイン
                        \Session::set('user_id', $user_id);
                        \Session::set_flash('success', '冒険者登録が完了しました！クエストを開始しましょう！');
                        \Response::redirect('dashboard');
                    }
                    else
                    {
                        $data['errors'][] = 'このユーザー名は既に使用されています。';
                    }
                }
                else
                {
                    $data['errors'] = $errors;
                }

                $data['username'] = $username; // 入力値を保持
            }
        }

        $this->template->title = 'Register';
        $this->template->content = \View::forge('auth/register', $data);
    }

    /**
     * ログアウト処理
     */
    public function action_logout()
    {
        // 【解説: Session::delete() で特定のセッションデータを削除】
        // ログアウト時は user_id を削除するだけで十分
        // Session::destroy() を使うとセッション全体が破棄される
        \Session::delete('user_id');
        \Session::set_flash('success', 'ログアウトしました。またの冒険をお待ちしています！');
        \Response::redirect('auth/login');
    }

    /**
     * 登録フォームのバリデーション
     *
     * @param string $username ユーザー名
     * @param string $password パスワード
     * @param string $password_confirm パスワード確認
     * @return array エラーメッセージの配列
     */
    protected static function validate_registration($username, $password, $password_confirm)
    {
        $errors = array();

        // ユーザー名のバリデーション
        if (empty($username))
        {
            $errors[] = 'ユーザー名を入力してください。';
        }
        elseif (mb_strlen($username) < 3 || mb_strlen($username) > 20)
        {
            $errors[] = 'ユーザー名は3文字以上20文字以下で入力してください。';
        }
        elseif ( ! preg_match('/^[a-zA-Z0-9_]+$/', $username))
        {
            $errors[] = 'ユーザー名は半角英数字とアンダースコアのみ使用できます。';
        }

        // パスワードのバリデーション
        if (empty($password))
        {
            $errors[] = 'パスワードを入力してください。';
        }
        elseif (strlen($password) < 6)
        {
            $errors[] = 'パスワードは6文字以上で入力してください。';
        }

        // パスワード確認
        if ($password !== $password_confirm)
        {
            $errors[] = 'パスワードが一致しません。';
        }

        return $errors;
    }
}
