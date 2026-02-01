<?php
/**
 * Quest-Logic ユーザーモデル (Model_User)
 *
 * 【解説: モデルの役割】
 * モデルはデータベースとのやり取りを担当するクラスです。
 * コントローラーから直接SQLを発行するのではなく、モデルに処理を集約することで
 * コードの再利用性と保守性が向上します。
 *
 * 【解説: ORM禁止の対応】
 * このプロジェクトではORMの使用が禁止されているため、
 * FuelPHPの DB クラス（クエリビルダ）を使用してSQLを発行します。
 */
class Model_User
{
    /**
     * テーブル名
     * 定数として定義することで、テーブル名の変更に強くなる
     */
    const TABLE_NAME = 'users';

    /**
     * ユーザー登録処理
     *
     * @param array $data ユーザー情報 ('username', 'password' を含む)
     * @return int|false 登録成功時は新規ユーザーID、失敗時はfalse
     */
    public static function register(array $data)
    {
        // バリデーション: 必須項目のチェック
        if (empty($data['username']) || empty($data['password']))
        {
            return false;
        }

        // ユーザー名の重複チェック
        if (static::find_by_username($data['username']))
        {
            return false;
        }

        // 【解説: パスワードのハッシュ化】
        // 平文パスワードをそのままDBに保存するのはセキュリティ上危険。
        // password_hash() でハッシュ化することで、DBが漏洩しても
        // 元のパスワードを復元することが困難になる。
        // PASSWORD_DEFAULT は現時点で最も安全なアルゴリズムを自動選択する。
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        // 【解説: DB::insert() の使い方】
        // DB::insert(テーブル名) でINSERT文を構築開始
        // ->set(配列) で挿入するカラムと値を指定
        // ->execute() で実行し、結果として [挿入されたID, 影響行数] の配列が返る
        $result = \DB::insert(static::TABLE_NAME)
            ->set(array(
                'username'   => $data['username'],
                'password'   => $hashed_password,
                'level'      => 1,    // 初期レベル
                'xp'         => 0,    // 初期経験値
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ))
            ->execute();

        // $result[0] に挿入されたレコードのIDが格納されている
        return isset($result[0]) ? $result[0] : false;
    }

    /**
     * ログイン認証処理
     *
     * @param string $username ユーザー名
     * @param string $password パスワード（平文）
     * @return array|false 認証成功時はユーザー情報の配列、失敗時はfalse
     */
    public static function login($username, $password)
    {
        // 入力値チェック
        if (empty($username) || empty($password))
        {
            return false;
        }

        // ユーザー名でユーザーを検索
        $user = static::find_by_username($username);

        if ( ! $user)
        {
            // ユーザーが見つからない場合
            return false;
        }

        // 【解説: password_verify() でのパスワード検証】
        // password_hash() でハッシュ化されたパスワードと、
        // 入力された平文パスワードを比較する。
        // ハッシュ化アルゴリズムが変わっても正しく検証できる。
        if ( ! password_verify($password, $user['password']))
        {
            // パスワードが一致しない
            return false;
        }

        // 認証成功: パスワードを除いたユーザー情報を返す
        unset($user['password']);
        return $user;
    }

    /**
     * ユーザー名でユーザーを検索
     *
     * @param string $username ユーザー名
     * @return array|null ユーザー情報、見つからない場合はnull
     */
    public static function find_by_username($username)
    {
        // 【解説: DB::select() の使い方】
        // DB::select() でSELECT文を構築
        // ->from(テーブル名) でテーブルを指定
        // ->where(カラム, 演算子, 値) で条件を指定
        // ->execute() で実行し、結果セットを取得
        // ->current() で最初の1行を連想配列で取得（見つからない場合はnull）
        $result = \DB::select()
            ->from(static::TABLE_NAME)
            ->where('username', '=', $username)
            ->execute()
            ->current();

        return $result ? $result : null;
    }

    /**
     * IDでユーザーを検索
     *
     * @param int $id ユーザーID
     * @return array|null ユーザー情報、見つからない場合はnull
     */
    public static function find_by_id($id)
    {
        $result = \DB::select('id', 'username', 'level', 'xp', 'created_at')
            ->from(static::TABLE_NAME)
            ->where('id', '=', $id)
            ->execute()
            ->current();

        return $result ? $result : null;
    }

    /**
     * ユーザー情報を更新
     *
     * @param int $id ユーザーID
     * @param array $data 更新するデータ
     * @return int 更新された行数
     */
    public static function update($id, array $data)
    {
        // 更新可能なカラムをホワイトリストで制限
        $allowed_columns = array('level', 'xp', 'updated_at');
        $update_data = array();

        foreach ($allowed_columns as $column)
        {
            if (isset($data[$column]))
            {
                $update_data[$column] = $data[$column];
            }
        }

        // 更新日時を自動セット
        $update_data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($update_data))
        {
            return 0;
        }

        // 【解説: DB::update() の使い方】
        // DB::update(テーブル名) でUPDATE文を構築
        // ->set(配列) で更新するカラムと値を指定
        // ->where() で更新対象を絞り込み
        // ->execute() で実行し、影響を受けた行数が返る
        $affected_rows = \DB::update(static::TABLE_NAME)
            ->set($update_data)
            ->where('id', '=', $id)
            ->execute();

        return $affected_rows;
    }

    /**
     * 経験値を加算
     *
     * @param int $user_id ユーザーID
     * @param int $xp 加算する経験値
     * @return bool 成功時true
     */
    public static function add_xp($user_id, $xp)
    {
        // 【解説: DB::expr() の使い方】
        // DB::expr() を使うと、SQL関数や計算式をそのまま埋め込める
        // ここでは現在のxpに加算する処理を行う
        $affected_rows = \DB::update(static::TABLE_NAME)
            ->set(array(
                'xp'         => \DB::expr('xp + ' . (int)$xp),
                'updated_at' => date('Y-m-d H:i:s'),
            ))
            ->where('id', '=', $user_id)
            ->execute();

        return $affected_rows > 0;
    }

    /**
     * レベルを更新
     *
     * @param int $user_id ユーザーID
     * @param int $new_level 新しいレベル
     * @return bool 成功時true
     */
    public static function set_level($user_id, $new_level)
    {
        $affected_rows = \DB::update(static::TABLE_NAME)
            ->set(array(
                'level'      => (int)$new_level,
                'updated_at' => date('Y-m-d H:i:s'),
            ))
            ->where('id', '=', $user_id)
            ->execute();

        return $affected_rows > 0;
    }
}
