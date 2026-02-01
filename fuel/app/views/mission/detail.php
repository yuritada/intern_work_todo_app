<!--
    Quest-Logic ミッション詳細画面（ボス攻略画面）

    【解説: Knockout.js との連携】
    この画面では Knockout.js を使用して、タスク完了時にページをリロードせずに
    HPバーをリアルタイムで更新します。data-bind 属性でViewModelとHTMLを紐づけます。
-->

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard'); ?>" class="text-light">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard/project/' . $boss['project_id']); ?>" class="text-light"><?php echo \Security::htmlentities($boss['project_title']); ?></a></li>
                <li class="breadcrumb-item active text-muted"><?php echo \Security::htmlentities($boss['title']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- ボス情報 -->
<div class="card card-quest mb-4" id="boss-view">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-danger"><?php echo \Security::htmlentities($boss['title']); ?></h3>
            <span class="badge bg-<?php echo $boss['boss_rank'] >= 4 ? 'danger' : ($boss['boss_rank'] >= 2 ? 'warning text-dark' : 'secondary'); ?> me-2">
                <?php echo \Security::htmlentities($rank_info['label']); ?>
            </span>
            <?php if ($boss['deadline']): ?>
            <span class="badge bg-info">期限: <?php echo date('Y/m/d', strtotime($boss['deadline'])); ?></span>
            <?php endif; ?>
            <?php if ($boss['done']): ?>
            <span class="badge bg-success ms-2">討伐済</span>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <a href="<?php echo \Uri::create('mission/edit/' . $boss['id']); ?>" class="btn btn-outline-light btn-sm">編集</a>
            <form action="<?php echo \Uri::create('mission/delete/' . $boss['id']); ?>" method="POST" class="d-inline"
                  onsubmit="return confirm('このボスを削除しますか？');">
                <?php echo \Form::csrf(); ?>
                <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <!-- HPバー（Knockout.jsでバインド） -->
        <div class="boss-hp-section mb-4">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-light fs-5">HP</span>
                <span class="text-light fs-5" data-bind="text: currentHp() + ' / ' + maxHp()">
                    <?php echo $boss['current_hp']; ?> / <?php echo $boss['boss_hp']; ?>
                </span>
            </div>
            <!--
                【解説: data-bind による動的スタイル変更】
                style バインディングでCSS widthを動的に設定
                hpPercent() は ViewModel の computed で計算された値
            -->
            <div class="boss-hp-bar-container">
                <div class="boss-hp-bar"
                     data-bind="style: { width: hpPercent() + '%' }, css: hpColorClass()">
                </div>
            </div>
        </div>

        <!-- 進捗表示 -->
        <div class="row text-center">
            <div class="col-6">
                <h4 class="text-light" data-bind="text: completedTasks() + ' / ' + totalTasks()">
                    <?php echo $boss['completed_tasks']; ?> / <?php echo $boss['total_tasks']; ?>
                </h4>
                <p class="text-muted mb-0">完了タスク</p>
            </div>
            <div class="col-6">
                <h4 class="text-success" data-bind="text: hpPercent() + '%'">
                    <?php echo $boss['hp_percent']; ?>%
                </h4>
                <p class="text-muted mb-0">残りHP</p>
            </div>
        </div>
    </div>
</div>

<!-- レベルアップ通知（非表示、JSで制御） -->
<div class="alert alert-success d-none" id="level-up-alert">
    <strong>Level Up!</strong> <span id="level-up-message"></span>
</div>

<!-- タスク一覧（攻撃アクション） -->
<div class="card card-quest">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">攻撃アクション（タスク）</h5>
        <button class="btn btn-quest btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            + 攻撃を追加
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($boss['children'])): ?>
        <div class="text-center py-4">
            <p class="text-light mb-3">まだ攻撃アクションがありません。</p>
            <button class="btn btn-quest" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                + 最初の攻撃を追加
            </button>
        </div>
        <?php else: ?>
        <!--
            【解説: foreach バインディング】
            tasks 配列の各要素に対してHTML要素を繰り返し生成します。
            $data で現在の要素、$index で配列のインデックスにアクセスできます。
        -->
        <div class="task-list" data-bind="foreach: tasks">
            <div class="task-item d-flex align-items-center p-3 mb-2" data-bind="css: { 'task-completed': done() }">
                <!--
                    【解説: click バインディング】
                    チェックボックスクリック時に toggleTask 関数を実行
                    これによりAjax通信が発生し、サーバーと同期します
                -->
                <input type="checkbox"
                       class="form-check-input me-3 task-checkbox"
                       data-bind="checked: done, click: $parent.toggleTask">
                <div class="flex-grow-1">
                    <span class="task-title" data-bind="text: title, css: { 'text-decoration-line-through': done() }"></span>
                    <span class="badge bg-warning text-dark ms-2" data-bind="text: 'DMG: ' + weight()"></span>
                </div>
                <form data-bind="attr: { action: '<?php echo \Uri::base(); ?>mission/delete_task/' + id() }" method="POST" class="d-inline">
                    <input type="hidden" name="<?php echo \Config::get('security.csrf_token_key', 'fuel_csrf_token'); ?>" value="<?php echo \Security::fetch_token(); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('この攻撃を削除しますか？');">
                        削除
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo \Uri::create('dashboard/project/' . $boss['project_id']); ?>" class="btn btn-outline-light">
        プロジェクトに戻る
    </a>
</div>

<!-- タスク追加モーダル -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">攻撃を追加</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo \Uri::create('mission/add_task/' . $boss['id']); ?>" method="POST">
                <?php echo \Form::csrf(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="task_title" class="form-label">攻撃名（タスク名）</label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               id="task_title"
                               name="title"
                               placeholder="例: デザイン案を作成する"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="task_weight" class="form-label">ダメージ量（1〜100）</label>
                        <input type="number"
                               class="form-control bg-dark text-light border-secondary"
                               id="task_weight"
                               name="weight"
                               value="10"
                               min="1"
                               max="100">
                        <div class="form-text text-muted">タスク完了時にボスに与えるダメージ</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-quest">追加する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .boss-hp-bar-container {
        height: 30px;
        background: #333;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .boss-hp-bar {
        height: 100%;
        border-radius: 15px;
        transition: width 0.5s ease, background 0.3s ease;
    }

    .boss-hp-bar.hp-high {
        background: linear-gradient(90deg, #10b981, #34d399);
    }

    .boss-hp-bar.hp-medium {
        background: linear-gradient(90deg, #f59e0b, #fbbf24);
    }

    .boss-hp-bar.hp-low {
        background: linear-gradient(90deg, #ef4444, #f87171);
    }

    .task-item {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        border-left: 3px solid #6366f1;
        transition: all 0.2s;
    }

    .task-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .task-item.task-completed {
        opacity: 0.6;
        border-left-color: #10b981;
    }

    .task-checkbox {
        width: 24px;
        height: 24px;
        cursor: pointer;
    }

    .task-title {
        color: #eaeaea;
        font-size: 1.1rem;
    }

    #level-up-alert {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 1050;
        animation: slideIn 0.5s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>

<!--
    【解説: Knockout.js ViewModel】
    ページ下部でViewModelを定義し、ko.applyBindings() でHTMLとバインドします。
    observableを使用することで、値の変更が自動的にUIに反映されます。
-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ViewModel定義
    function BattleViewModel() {
        var self = this;

        // Observable データ（値の変更を監視）
        self.currentHp = ko.observable(<?php echo (int)$boss['current_hp']; ?>);
        self.maxHp = ko.observable(<?php echo (int)$boss['boss_hp']; ?>);
        self.completedTasks = ko.observable(<?php echo (int)$boss['completed_tasks']; ?>);
        self.totalTasks = ko.observable(<?php echo (int)$boss['total_tasks']; ?>);
        self.isDead = ko.observable(<?php echo $boss['done'] ? 'true' : 'false'; ?>);

        // タスク配列（observableArray）
        self.tasks = ko.observableArray([
            <?php foreach ($boss['children'] as $child): ?>
            {
                id: ko.observable(<?php echo (int)$child['id']; ?>),
                title: ko.observable('<?php echo addslashes($child['title']); ?>'),
                weight: ko.observable(<?php echo (int)$child['weight']; ?>),
                done: ko.observable(<?php echo $child['done'] ? 'true' : 'false'; ?>)
            },
            <?php endforeach; ?>
        ]);

        // Computed: HP割合を計算
        self.hpPercent = ko.computed(function() {
            if (self.maxHp() === 0) return 0;
            return Math.round((self.currentHp() / self.maxHp()) * 100);
        });

        // Computed: HPバーの色クラス
        self.hpColorClass = ko.computed(function() {
            var percent = self.hpPercent();
            if (percent > 50) return 'hp-high';
            if (percent > 20) return 'hp-medium';
            return 'hp-low';
        });

        // タスク完了/未完了をトグル
        self.toggleTask = function(task) {
            var newDone = !task.done();

            // 【解説: Ajax通信】
            // jQueryの$.ajaxでサーバーと非同期通信
            // 成功時にViewModelを更新し、UIが自動的に変更される
            $.ajax({
                url: '<?php echo \Uri::base(); ?>api/battle/' + (newDone ? 'attack' : 'undo'),
                type: 'POST',
                data: {
                    task_id: task.id(),
                    <?php echo \Config::get('security.csrf_token_key', 'fuel_csrf_token'); ?>: '<?php echo \Security::fetch_token(); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // タスクの状態を更新
                        task.done(newDone);

                        // HPを更新
                        self.currentHp(response.new_hp);

                        // 完了数を更新
                        if (newDone) {
                            self.completedTasks(self.completedTasks() + 1);
                        } else {
                            self.completedTasks(self.completedTasks() - 1);
                        }

                        // ボス討伐時
                        if (response.is_dead) {
                            self.isDead(true);
                            showVictoryMessage(response.gained_xp);
                        }

                        // レベルアップ時
                        if (response.level_up) {
                            showLevelUpMessage(response.new_level);
                        }
                    } else {
                        alert('エラーが発生しました: ' + (response.error || '不明なエラー'));
                        // チェックボックスを元に戻す
                        task.done(!newDone);
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                    task.done(!newDone);
                }
            });

            // イベントの伝播を止める（チェックボックスのデフォルト動作を防ぐ）
            return true;
        };
    }

    // ViewModelをHTMLにバインド
    ko.applyBindings(new BattleViewModel(), document.getElementById('boss-view'));
    ko.applyBindings(new BattleViewModel(), document.querySelector('.task-list'));

    // 勝利メッセージ表示
    function showVictoryMessage(xp) {
        var alert = document.getElementById('level-up-alert');
        alert.innerHTML = '<strong>Victory!</strong> ボスを討伐しました！ +' + xp + ' XP獲得！';
        alert.classList.remove('d-none', 'alert-success');
        alert.classList.add('alert-warning');
        setTimeout(function() {
            alert.classList.add('d-none');
        }, 5000);
    }

    // レベルアップメッセージ表示
    function showLevelUpMessage(newLevel) {
        var alert = document.getElementById('level-up-alert');
        alert.innerHTML = '<strong>Level Up!</strong> レベルが ' + newLevel + ' になりました！';
        alert.classList.remove('d-none');
        setTimeout(function() {
            alert.classList.add('d-none');
        }, 5000);
    }
});
</script>
