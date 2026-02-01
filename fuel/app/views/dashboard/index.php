<!--
    Quest-Logic ダッシュボード画面
    RPGのステータス画面をイメージした冒険の拠点
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
                    <h4 class="text-light"><?php echo \Security::htmlentities($current_user['username']); ?></h4>
                    <span class="badge bg-primary fs-6">Level <?php echo \Security::htmlentities($current_user['level']); ?></span>
                </div>

                <div class="status-bars">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-light">経験値 (XP)</small>
                            <small class="text-light">
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
                <?php if ( ! empty($active_bosses)): ?>
                <span class="badge bg-danger pulse-badge">WARNING</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($active_bosses)): ?>
                <div class="text-center py-5">
                    <p class="text-light mb-3">現在、遭遇中のボスはいません。</p>
                    <p class="text-light">新しいプロジェクト（冒険）を開始して、<br>クエストを進めましょう！</p>
                    <a href="<?php echo \Uri::create('dashboard/create'); ?>" class="btn btn-quest mt-3">
                        + 新しい冒険を開始
                    </a>
                </div>
                <?php else: ?>
                <div class="boss-list">
                    <?php foreach ($active_bosses as $boss): ?>
                    <div class="boss-item mb-3 p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="boss-name text-decoration-none">
                                    <?php echo \Security::htmlentities($boss['title']); ?>
                                </a>
                                <span class="badge bg-secondary ms-2"><?php echo \Security::htmlentities($boss['rank_label']); ?></span>
                                <br>
                                <small class="text-light">
                                    <?php echo \Security::htmlentities($boss['project_title']); ?>
                                </small>
                            </div>
                            <?php if ($boss['deadline']): ?>
                            <span class="badge <?php echo (strtotime($boss['deadline']) < strtotime('+3 days')) ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                <?php echo date('m/d', strtotime($boss['deadline'])); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="hp-bar-container">
                            <div class="hp-bar" style="width: <?php echo $boss['hp_percent']; ?>%;">
                            </div>
                        </div>
                        <small class="text-light">
                            HP: <?php echo $boss['current_hp']; ?> / <?php echo $boss['boss_hp']; ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
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
                <a href="<?php echo \Uri::create('dashboard/create'); ?>" class="btn btn-quest btn-sm">
                    + 新しい冒険
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($projects)): ?>
                <p class="text-light text-center py-4">
                    まだ冒険（プロジェクト）がありません。<br>
                    上のボタンから新しい冒険を開始しましょう！
                </p>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="project-card p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <a href="<?php echo \Uri::create('dashboard/project/' . $project['id']); ?>" class="project-title text-decoration-none">
                                    <?php echo \Security::htmlentities($project['title']); ?>
                                </a>
                                <?php if ($project['deadline']): ?>
                                <span class="badge bg-info">
                                    <?php echo date('m/d', strtotime($project['deadline'])); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-success"
                                     style="width: <?php echo $project['progress']['progress_percent']; ?>%;">
                                </div>
                            </div>
                            <small class="text-light">
                                ボス: <?php echo $project['progress']['defeated_bosses']; ?>/<?php echo $project['progress']['total_bosses']; ?> 討伐 |
                                タスク: <?php echo $project['progress']['completed_tasks']; ?>/<?php echo $project['progress']['total_tasks']; ?> 完了
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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

    .pulse-badge {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .boss-item {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        border-left: 3px solid #ef4444;
    }

    .boss-name {
        color: #f87171;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .boss-name:hover {
        color: #fca5a5;
    }

    .hp-bar-container {
        height: 12px;
        background: #333;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 4px;
    }

    .hp-bar {
        height: 100%;
        background: linear-gradient(90deg, #ef4444, #f97316);
        border-radius: 6px;
        transition: width 0.3s ease;
    }

    .project-card {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        border: 1px solid #333;
        transition: transform 0.2s, border-color 0.2s;
    }

    .project-card:hover {
        transform: translateY(-2px);
        border-color: #6366f1;
    }

    .project-title {
        color: #a5b4fc;
        font-weight: bold;
    }

    .project-title:hover {
        color: #c7d2fe;
    }
</style>
