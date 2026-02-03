<!-- プロジェクト詳細画面 -->
<?php
    // プロジェクト完全討伐判定
    $is_project_cleared = ($progress['total_bosses'] > 0 && $progress['defeated_bosses'] == $progress['total_bosses']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-light mb-1">
            <?php echo \Security::htmlentities($project['title']); ?>
            <?php if ($is_project_cleared): ?>
            <span class="badge bg-success fs-6 ms-2">完全討伐</span>
            <?php endif; ?>
        </h2>
        <?php if ($project['deadline']): ?>
        <span class="badge bg-info">期限: <?php echo date('Y/m/d', strtotime($project['deadline'])); ?></span>
        <?php endif; ?>
    </div>
    <div class="btn-group">
        <a href="<?php echo \Uri::create('dashboard/bulk_create/' . $project['id']); ?>" class="btn btn-quest btn-sm">
            一斉登録
        </a>
        <a href="<?php echo \Uri::create('dashboard/edit/' . $project['id']); ?>" class="btn btn-outline-light btn-sm">
            編集
        </a>
        <form action="<?php echo \Uri::create('dashboard/delete/' . $project['id']); ?>" method="POST" class="d-inline"
              onsubmit="return confirm('この冒険を終了しますか？関連するボスとタスクも全て削除されます。');">
            <?php echo \Form::csrf(); ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
        </form>
    </div>
</div>

<?php if ($project['memo']): ?>
<div class="card card-quest mb-4">
    <div class="card-body">
        <p class="text-light mb-0"><?php echo nl2br(\Security::htmlentities($project['memo'])); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- 進捗サマリー -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card card-quest text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $progress['progress_percent']; ?>%</h3>
                <p class="text-light mb-0">全体進捗</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-quest text-center">
            <div class="card-body">
                <h3 class="text-danger"><?php echo $progress['defeated_bosses']; ?> / <?php echo $progress['total_bosses']; ?></h3>
                <p class="text-light mb-0">討伐ボス</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-quest text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo $progress['completed_tasks']; ?> / <?php echo $progress['total_tasks']; ?></h3>
                <p class="text-light mb-0">完了タスク</p>
            </div>
        </div>
    </div>
</div>

<!-- ボス一覧 -->
<div class="card card-quest">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">ボス一覧</h5>
        <div class="btn-group">
            <a href="<?php echo \Uri::create('dashboard/bulk_create/' . $project['id']); ?>" class="btn btn-outline-light btn-sm">
                一斉登録
            </a>
            <a href="<?php echo \Uri::create('mission/create/' . $project['id']); ?>" class="btn btn-quest btn-sm">
                + ボスを追加
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($bosses)): ?>
        <div class="text-center py-4">
            <p class="text-light mb-3">まだボスがいません。</p>
            <a href="<?php echo \Uri::create('mission/create/' . $project['id']); ?>" class="btn btn-quest">
                + 最初のボスを追加
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover">
                <thead>
                    <tr>
                        <th>ボス名</th>
                        <th>ランク</th>
                        <th>HP</th>
                        <th>状態</th>
                        <th>期限</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bosses as $boss): ?>
                    <tr class="<?php echo $boss['done'] ? 'boss-defeated' : ''; ?>">
                        <td>
                            <a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="text-decoration-none <?php echo $boss['done'] ? 'text-secondary' : 'text-danger'; ?>">
                                <?php echo \Security::htmlentities($boss['title']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $boss['boss_rank'] >= 4 ? 'danger' : ($boss['boss_rank'] >= 2 ? 'warning text-dark' : 'secondary'); ?>">
                                <?php echo \Security::htmlentities($boss['rank_label']); ?>
                            </span>
                        </td>
                        <td style="width: 200px;">
                            <div class="hp-bar-container-sm">
                                <div class="hp-bar" style="width: <?php echo $boss['hp_percent']; ?>%;"></div>
                            </div>
                            <small class="text-light"><?php echo $boss['current_hp']; ?>/<?php echo $boss['boss_hp']; ?></small>
                        </td>
                        <td>
                            <?php if ($boss['done']): ?>
                            <span class="badge bg-success">討伐済</span>
                            <?php else: ?>
                            <span class="badge bg-danger">遭遇中</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-light">
                            <?php if ($boss['deadline']): ?>
                            <?php echo date('m/d', strtotime($boss['deadline'])); ?>
                            <?php else: ?>
                            <span class="text-secondary">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="btn btn-outline-light btn-sm">
                                攻略
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo \Uri::create('dashboard'); ?>" class="btn btn-outline-light">
        ダッシュボードに戻る
    </a>
</div>

<style>
    .hp-bar-container-sm {
        height: 8px;
        background: #333;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 2px;
    }

    .hp-bar {
        height: 100%;
        background: linear-gradient(90deg, #ef4444, #f97316);
        border-radius: 4px;
    }

    .table-dark {
        --bs-table-bg: transparent;
    }

    .table-dark th {
        color: #c7d2fe;
    }

    .boss-defeated {
        opacity: 0.7;
    }

    /* 配色修正: text-secondary を暗い背景用に調整 */
    .text-secondary {
        color: #9ca3af !important;
    }
</style>
