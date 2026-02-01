<!--
    Quest-Logic 新規登録画面

    【解説: 入力値の保持】
    バリデーションエラー時、ユーザーが入力した値を再表示することで
    UXを向上させています。ただし、パスワードは安全のため再表示しません。
-->

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card card-quest">
            <div class="card-header text-center">
                <h2 class="mb-0">Quest-Logic</h2>
                <p class="text-light mb-0">冒険者登録</p>
            </div>
            <div class="card-body p-4">

                <?php if ( ! empty($errors)): ?>
                <!--
                    【解説: 複数エラーの表示】
                    バリデーションエラーは配列で渡されるため、
                    ループで全てのエラーを表示します。
                -->
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                        <li><?php echo \Security::htmlentities($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="<?php echo \Uri::create('auth/register'); ?>" method="POST">
                    <?php echo \Form::csrf(); ?>

                    <div class="mb-3 text-light">
                        <label for="username" class="form-label">ユーザー名</label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               id="username"
                               name="username"
                               value="<?php echo \Security::htmlentities($username); ?>"
                               placeholder="3〜20文字の半角英数字"
                               minlength="3"
                               maxlength="20"
                               pattern="[a-zA-Z0-9_]+"
                               required
                               autofocus>
                        <div class="form-text text-light">
                            半角英数字とアンダースコア（_）のみ使用できます
                        </div>
                    </div>

                    <div class="mb-3 text-light">
                        <label for="password" class="form-label">パスワード</label>
                        <input type="password"
                               class="form-control bg-dark text-light border-secondary"
                               id="password"
                               name="password"
                               placeholder="6文字以上"
                               minlength="6"
                               required>
                        <div class="form-text text-light">
                            6文字以上で設定してください
                        </div>
                    </div>

                    <div class="mb-4 text-light ">
                        <label for="password_confirm" class="form-label">パスワード（確認）</label>
                        <input type="password"
                               class="form-control bg-dark text-light border-secondary"
                               id="password_confirm"
                               name="password_confirm"
                               placeholder="もう一度入力"
                               minlength="6"
                               required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-quest btn-lg">
                            冒険者登録
                        </button>
                    </div>
                </form>

                <hr class="my-4 border-secondary">

                <div class="text-center text-light">
                    <p class="mb-0 text-light">
                        既に冒険者登録済みですか？
                    </p>
                    <a href="<?php echo \Uri::create('auth/login'); ?>" class="btn btn-outline-light mt-2">
                        ログイン
                    </a>
                </div>

            </div>
        </div>

        <!-- 登録の特典を表示（ゲーミフィケーション要素） -->
        <div class="card card-quest mt-4">
            <div class="card-body">
                <h5 class="card-title text-center mb-3">冒険者特典</h5>
                <ul class="list-unstyled mb-0 text-light">
                    <li class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2">Lv.1</span>
                        初期レベル1からスタート
                    </li>
                    <li class="d-flex align-items-center mb-2">
                        <span class="badge bg-warning text-dark me-2">XP</span>
                        タスク完了で経験値獲得
                    </li>
                    <li class="d-flex align-items-center">
                        <span class="badge bg-danger me-2">BOSS</span>
                        ボス討伐でレベルアップ
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .card-quest .card-header h2 {
        color: #6366f1;
        font-weight: bold;
        text-shadow: 0 0 10px rgba(99, 102, 241, 0.3);
    }

    .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
    }

    .btn-quest {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        border: none;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-quest:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    }

    .card-quest .badge {
        min-width: 50px;
    }
</style>