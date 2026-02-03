<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? \Security::htmlentities($title) . ' | ' : ''; ?>Quest-Logic</title>

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- カスタムスタイル -->
    <style>
        :root {
            --quest-primary: #6366f1;
            --quest-secondary: #4f46e5;
            --quest-success: #10b981;
            --quest-warning: #f59e0b;
            --quest-danger: #ef4444;
        }

        body {
            background-color: #1a1a2e;
            color: #eaeaea;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* ナビゲーションバー */
        .navbar-quest {
            background: linear-gradient(135deg, #16213e 0%, #1a1a2e 100%);
            border-bottom: 2px solid var(--quest-primary);
        }

        .navbar-brand {
            font-weight: bold;
            color: var(--quest-primary) !important;
        }

        /* ユーザーステータス表示 */
        .user-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #eaeaea;
        }

        .user-level {
            background: var(--quest-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: bold;
        }

        .xp-bar-container {
            width: 120px;
            height: 8px;
            background: #333;
            border-radius: 4px;
            overflow: hidden;
        }

        .xp-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--quest-success), #34d399);
            transition: width 0.3s ease;
        }

        .xp-text {
            color: #a5b4fc;
            font-size: 0.75rem;
        }

        /* メインコンテンツ */
        .main-content {
            padding: 2rem 0;
        }

        /* カード（RPG風） */
        .card-quest {
            background: #16213e;
            border: 1px solid #333;
            border-radius: 0.5rem;
        }

        .card-quest .card-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-bottom: 1px solid var(--quest-primary);
        }

        /* ボタン */
        .btn-quest {
            background: var(--quest-primary);
            border: none;
            color: white;
        }

        .btn-quest:hover {
            background: var(--quest-secondary);
            color: white;
        }

        /* フッター */
        .footer-quest {
            background: #16213e;
            border-top: 1px solid #333;
            padding: 1rem 0;
            margin-top: auto;
        }

        /* 配色修正: text-muted を暗い背景用に調整 */
        .text-muted {
            color: #9ca3af !important;
        }

        /* ナビゲーションリンクの色 */
        .navbar-quest .nav-link {
            color: #c7d2fe !important;
        }
        .navbar-quest .nav-link:hover {
            color: #ffffff !important;
        }

        /* カードヘッダーのテキスト色 */
        .card-quest .card-header h5,
        .card-quest .card-header h3 {
            color: #e2e8f0;
        }

        /* フッターテキストの可読性向上 */
        .footer-quest .text-muted {
            color: #a0aec0 !important;
        }

        /* フォーム要素の色調整 */
        .form-control::placeholder {
            color: #6b7280 !important;
        }
        .form-label {
            color: #e2e8f0;
        }
        .form-text {
            color: #9ca3af !important;
        }

        /* バッジの可読性 */
        .badge.bg-secondary {
            background-color: #4b5563 !important;
            color: #f3f4f6 !important;
        }
        .badge.bg-info {
            background-color: #0ea5e9 !important;
            color: #ffffff !important;
        }

        /* レベルアップアニメーション */
        .user-level.level-up-animation {
            animation: levelUpPulse 0.5s ease 3;
        }

        @keyframes levelUpPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); background: #fbbf24; }
        }

        /* XPバー更新アニメーション */
        .xp-bar {
            transition: width 0.5s ease;
        }

        .xp-bar.xp-gain-animation {
            animation: xpGain 0.3s ease;
        }

        @keyframes xpGain {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.5); }
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
    </style>

    <?php if (isset($extra_css)) echo $extra_css; ?>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/knockout/3.5.0/knockout-min.js"></script>
</head>
<body>
    <!-- ナビゲーションバー -->
    <nav class="navbar navbar-expand-lg navbar-quest">
        <div class="container">
            <a class="navbar-brand" href="<?php echo \Uri::base(); ?>dashboard">Quest-Logic</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isset($current_user) && $current_user): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo \Uri::base(); ?>dashboard">Dashboard</a>
                    </li>
                </ul>

                <!-- ユーザーステータス -->
                <div class="user-status">
                    <span class="user-level">Lv.<?php echo \Security::htmlentities($current_user['level']); ?></span>
                    <div>
                        <small><?php echo \Security::htmlentities($current_user['username']); ?></small>
                        <div class="xp-bar-container">
                            <div class="xp-bar" style="width: <?php echo isset($xp_progress) ? $xp_progress : 0; ?>%;"></div>
                        </div>
                        <small class="xp-text">
                            XP: <?php echo \Security::htmlentities($current_user['xp']); ?>
                            <?php if (isset($next_level_xp)): ?>
                            / <?php echo \Security::htmlentities($next_level_xp); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <a href="<?php echo \Uri::base(); ?>auth/logout" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
                <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo \Uri::base(); ?>auth/login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo \Uri::base(); ?>auth/register">Register</a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <main class="main-content">
        <div class="container">
            <?php
            /**
             * フラッシュメッセージの表示
             * Session::set_flash() で設定されたメッセージを表示
             */
            $flash_success = \Session::get_flash('success');
            $flash_error = \Session::get_flash('error');
            ?>

            <?php if ($flash_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo \Security::htmlentities($flash_success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo \Security::htmlentities($flash_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php
            /**
             * 【解説】
             * $content はコントローラーのアクションで設定された View が
             * Controller_Template によって自動的にここに挿入される
             */
            echo isset($content) ? $content : '';
            ?>
        </div>
    </main>

    <!-- フッター -->
    <footer class="footer-quest">
        <div class="container text-center text-muted">
            <small>&copy; <?php echo date('Y'); ?> Quest-Logic - RPG Task Management</small>
        </div>
    </footer>

    <!-- Bootstrap JS（Bootstrap はDOM操作が必要なので body 末尾で読み込み） -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
