<!--
    Quest-Logic ログイン画面

    【解説: ビューの役割】
    ビューはユーザーに表示するHTMLを生成する役割を担います。
    コントローラーから渡された変数（$error, $username等）を使用して
    動的にコンテンツを生成します。

    【解説: Security::htmlentities() の重要性】
    ユーザー入力をそのまま表示すると XSS（クロスサイトスクリプティング）攻撃の
    リスクがあります。Security::htmlentities() を使用することで、
    HTMLの特殊文字（<, >, & 等）をエスケープし、安全に表示できます。
-->

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card card-quest">
            <div class="card-header text-center">
                <h2 class="mb-0">Quest-Logic</h2>
                <p class="text-light mb-0">冒険者ログイン</p>
            </div>
            <div class="card-body p-4">

                <?php if ( ! empty($error)): ?>
                <!--
                    【解説: エラーメッセージの表示】
                    コントローラーから渡された $error を表示
                    必ず htmlentities() でエスケープする
                -->
                <div class="alert alert-danger">
                    <?php echo \Security::htmlentities($error); ?>
                </div>
                <?php endif; ?>

                <!--
                    【解説: CSRF対策】
                    \Form::csrf() は隠しフィールドとしてCSRFトークンを出力します。
                    このトークンはセッションと紐づいており、
                    POSTリクエスト時に Security::check_token() で検証することで
                    外部サイトからの不正なリクエスト（CSRF攻撃）を防ぎます。
                -->
                <form action="<?php echo \Uri::create('auth/login'); ?>" method="POST" id="loginForm" onsubmit="return handleLoginSubmit();">
                    <?php echo \Form::csrf(); ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">ユーザー名</label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               id="username"
                               name="username"
                               value="<?php echo \Security::htmlentities($username); ?>"
                               placeholder="adventurer"
                               required
                               autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">パスワード</label>
                        <input type="password"
                               class="form-control bg-dark text-light border-secondary"
                               id="password"
                               name="password"
                               placeholder="********"
                               required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-quest btn-lg" id="loginSubmitBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" id="loginSpinner"></span>
                            <span id="loginBtnText">ログイン</span>
                        </button>
                    </div>
                </form>

                <hr class="my-4 border-secondary">

                <div class="text-center text-light">
                    <p class="mb-0 text-light">
                        まだ冒険者登録をしていませんか？
                    </p>
                    <a href="<?php echo \Uri::create('auth/register'); ?>" class="btn btn-outline-light mt-2">
                        新規登録
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- RPG風の装飾用スタイル -->
<style>
    /* カード全体の背景を暗くし、文字を白くする */
    .card-quest {
        background-color: #1a1a2e; /* 濃い紺色 */
        border: 1px solid #6366f1;
        color: #ffffff;
    }

    .card-quest .card-header {
        border-bottom: 1px solid rgba(99, 102, 241, 0.3);
    }

    .card-quest .card-header h2 {
        color: #6366f1;
        font-weight: bold;
        text-shadow: 0 0 10px rgba(99, 102, 241, 0.3);
    }

    /* text-muted を上書き、または別のクラスを作る */
    .card-quest .text-muted {
        color: #a0a0c0 !important; /* 背景に馴染みつつ読める明るいグレー */
    }

    .form-control {
        background-color: #0f0f1a !important; /* 入力欄をさらに暗く */
        color: #ffffff !important;
    }

    .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
    }

    .btn-quest {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        border: none;
        color: white !important; /* 文字色は必ず白 */
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-quest:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        color: white !important;
    }
</style>

<script>
/**
 * 【二重送信防止】
 * フォーム送信時にボタンを無効化し、スピナーを表示。
 */
var isSubmitting = false;

function handleLoginSubmit() {
    if (isSubmitting) {
        return false;
    }
    isSubmitting = true;

    var btn = document.getElementById('loginSubmitBtn');
    var spinner = document.getElementById('loginSpinner');
    var btnText = document.getElementById('loginBtnText');

    if (btn) btn.disabled = true;
    if (spinner) spinner.classList.remove('d-none');
    if (btnText) btnText.textContent = 'ログイン中...';

    return true;
}

// ページ表示時に状態をリセット（ブラウザの戻るボタン対策）
window.addEventListener('pageshow', function(event) {
    isSubmitting = false;
    var btn = document.getElementById('loginSubmitBtn');
    var spinner = document.getElementById('loginSpinner');
    var btnText = document.getElementById('loginBtnText');

    if (btn) btn.disabled = false;
    if (spinner) spinner.classList.add('d-none');
    if (btnText) btnText.textContent = 'ログイン';
});
</script>
