<?php
/**
 * Quest-Logic ルーティング設定
 *
 * 【解説: FuelPHPのルーティング】
 * ルーティングはURLとコントローラー/アクションのマッピングを定義します。
 * '_root_' はドメイン直下（例: http://example.com/）へのアクセス時のルート
 * '_404_' は存在しないページへのアクセス時のルート
 */
return array(
    // ルートURLはダッシュボードへ（未ログインの場合はController_Baseでログイン画面へリダイレクト）
    '_root_'  => 'dashboard/index',

    // 404エラーページ
    '_404_'   => 'welcome/404',

    // 認証関連のルート（明示的に定義）
    'login'    => 'auth/login',
    'logout'   => 'auth/logout',
    'register' => 'auth/register',
);
