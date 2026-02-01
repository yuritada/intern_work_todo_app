<!--
    Quest-Logic ダッシュボード画面

    RPGのステータス画面をイメージした冒険の拠点。
    Phase 3 で詳細な実装を行います。
-->

<div class="row">
    <!-- プレイヤーステータス -->
    <div class="col-lg-4 mb-4">
        <div class="card card-quest h-100">
            <div class="card-header">
                <h5 class="mb-0">冒険者ステータス</h5>
            </div>
            <div class="card-body">
                <?php if (isset($current_user) && $current_user): ?>
                <div class="text-center mb-4">
                    <div class="player-avatar mb-3">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                    </div>
                    <h4><?php echo \Security::htmlentities($current_user['username']); ?></h4>
                    <span class="badge bg-primary fs-6">Level <?php echo \Security::htmlentities($current_user['level']); ?></span>
                </div>

                <div class="status-bars">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small>経験値 (XP)</small>
                            <small>
                                <?php echo \Security::htmlentities($current_user['xp']); ?>
                                <?php if (isset($next_level_xp)): ?>
                                / <?php echo \Security::htmlentities($next_level_xp); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success"
                                 role="progressbar"
                                 style="width: <?php echo isset($xp_progress) ? $xp_progress : 0; ?>%">
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-light">ログイン情報を読み込み中...</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 遭遇中のボス（緊急度の高いタスク） -->
    <div class="col-lg-8 mb-4">
        <div class="card card-quest h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">遭遇中のボス</h5>
                <span class="badge bg-danger">WARNING</span>
            </div>
            <div class="card-body">
                <?php if (empty($projects)): ?>
                <div class="text-center py-5">
                    <p class="text-light mb-3">現在、遭遇中のボスはいません。</p>
                    <p class="text-light">新しいプロジェクト（冒険）を開始して、<br>クエストを進めましょう！</p>
                    <!-- Phase 3 でプロジェクト作成機能を追加 -->
                    <button class="btn btn-quest mt-3" disabled>
                        + 新しい冒険を開始（準備中）
                    </button>
                </div>
                <?php else: ?>
                <!-- Phase 3 でボス一覧を表示 -->
                <div class="list-group list-group-flush">
                    <!-- TODO: ボス一覧をループで表示 -->
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- プロジェクト一覧 -->
<div class="row">
    <div class="col-12">
        <div class="card card-quest">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">冒険一覧（プロジェクト）</h5>
                <!-- Phase 3 でプロジェクト作成ボタンを有効化 -->
            </div>
            <div class="card-body">
                <?php if (empty($projects)): ?>
                <p class="text-light text-center py-4">
                    まだ冒険（プロジェクト）がありません。<br>
                    Phase 3 の実装で作成機能が追加されます。
                </p>
                <?php else: ?>
                <!-- Phase 3 でプロジェクト一覧を表示 -->
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-circle {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: bold;
        color: white;
        margin: 0 auto;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    }

    .progress {
        background-color: #333;
        border-radius: 10px;
    }

    .progress-bar {
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .card-quest .badge.bg-danger {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
</style>
