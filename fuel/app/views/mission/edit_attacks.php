<!--
    Quest-Logic 攻撃編集画面

    【Phase 5: UX向上 - 攻撃全体編集】
    既存の攻撃を表示し、追加・削除・ダメージ再計算ができます。
    自動バランスモードで、ボスHPを攻撃に比率分配します。
-->

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard'); ?>" class="text-light">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard/project/' . $boss['project_id']); ?>" class="text-light"><?php echo \Security::htmlentities($boss['project_title']); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="text-light"><?php echo \Security::htmlentities($boss['title']); ?></a></li>
                <li class="breadcrumb-item active" style="color: #a5b4fc;">攻撃を編集</li>
            </ol>
        </nav>
    </div>
</div>

<!-- ボス情報カード -->
<div class="card card-quest mb-4">
    <div class="card-header">
        <h4 class="mb-0 text-danger">
            <?php echo \Security::htmlentities($boss['title']); ?>
            <span class="badge bg-<?php echo $boss['boss_rank'] >= 4 ? 'danger' : ($boss['boss_rank'] >= 2 ? 'warning text-dark' : 'secondary'); ?> ms-2">
                <?php echo \Security::htmlentities($rank_info['label']); ?>
            </span>
            <span class="badge bg-secondary ms-2">HP: <?php echo $boss['boss_hp']; ?></span>
        </h4>
    </div>
    <div class="card-body" id="damage-mapping">
        <!-- ダメージマッピングバー -->
        <div class="damage-mapping-section mb-3">
            <div class="mapping-bar-container mb-2">
                <div class="mapping-bar-bg">
                    <div class="mapping-bar completed-damage"
                         data-bind="style: { width: completedDamagePercent() + '%' }">
                    </div>
                    <div class="mapping-bar pending-damage"
                         data-bind="style: { width: pendingDamagePercent() + '%', left: completedDamagePercent() + '%' }">
                    </div>
                </div>
                <div class="hp-limit-line"></div>
            </div>

            <div class="d-flex justify-content-between flex-wrap text-light small">
                <div>
                    <span class="legend-box completed"></span>
                    完了済み: <span data-bind="text: completedDamage">0</span> DMG
                </div>
                <div>
                    <span class="legend-box pending"></span>
                    未完了: <span data-bind="text: pendingDamage">0</span> DMG
                </div>
                <div>
                    <span class="legend-box total"></span>
                    合計: <span data-bind="text: totalDamage">0</span> / <?php echo $boss['boss_hp']; ?> HP
                </div>
            </div>
        </div>

        <!-- 自動バランス時の状態表示 -->
        <div class="alert alert-success mb-0" data-bind="visible: autoBalance() && validTaskCount() > 0">
            自動バランスON: 全攻撃を完了するとボスを討伐できます。
        </div>
        <div class="alert alert-warning mb-0" data-bind="visible: !autoBalance() && totalDamage() != <?php echo $boss['boss_hp']; ?> && validTaskCount() > 0">
            手動モード: 合計ダメージ（<span data-bind="text: totalDamage"></span>）がボスHP（<?php echo $boss['boss_hp']; ?>）と一致していません。
        </div>
    </div>
</div>

<!-- エラー表示 -->
<?php if ( ! empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?>
        <li><?php echo \Security::htmlentities($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 攻撃編集フォーム -->
<div class="card card-quest" id="edit-attacks-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">攻撃一覧</h5>
        <button type="button" class="btn btn-outline-light btn-sm" data-bind="click: addRow">
            + 行を追加
        </button>
    </div>
    <div class="card-body">
        <form action="<?php echo \Uri::create('mission/edit_attacks/' . $boss['id']); ?>" method="POST" id="editAttacksForm">
            <?php echo \Form::csrf(); ?>

            <!-- 自動バランスモード -->
            <div class="alert alert-primary mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoBalanceSwitch" name="auto_balance" value="1"
                           data-bind="checked: autoBalance">
                    <label class="form-check-label" for="autoBalanceSwitch">
                        <strong>自動バランスモード</strong>（推奨）
                    </label>
                </div>
                <div class="small mt-2" data-bind="visible: autoBalance">
                    心理的重みの<strong>比率</strong>でボスHP（<?php echo $boss['boss_hp']; ?>）を分配します。
                </div>
                <div class="small mt-2 text-muted" data-bind="visible: !autoBalance()">
                    心理的重みの値をそのまま攻撃力として使用します。
                </div>
            </div>

            <!-- 心理的重みの説明 -->
            <div class="alert alert-secondary mb-4">
                <h6 class="mb-2">心理的重みの目安</h6>
                <div class="row small">
                    <?php foreach ($psychological_weights as $key => $pw): ?>
                    <div class="col-md-4">
                        <strong><?php echo \Security::htmlentities($pw['label']); ?></strong>
                        <span data-bind="visible: !autoBalance()">(<?php echo $pw['weight']; ?> DMG)</span>
                        <span data-bind="visible: autoBalance()">(比率 <?php echo $pw['weight']; ?>)</span>
                        <br>
                        <span class="text-muted"><?php echo \Security::htmlentities($pw['description']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- タスク入力行 -->
            <div id="task-rows" data-bind="foreach: tasks">
                <div class="row mb-3 task-row" data-bind="css: { 'task-completed-row': done() }">
                    <div class="col-md-1 d-flex align-items-center">
                        <input type="checkbox" class="form-check-input"
                               data-bind="checked: done, attr: { name: 'tasks[' + $index() + '][done]' }"
                               value="1">
                    </div>
                    <div class="col-md-5">
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               data-bind="value: title, attr: { name: 'tasks[' + $index() + '][title]' }, css: { 'text-decoration-line-through': done() }"
                               placeholder="攻撃名を入力">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select bg-dark text-light border-secondary"
                                data-bind="value: weight, attr: { name: 'tasks[' + $index() + '][weight]' }">
                            <?php foreach ($psychological_weights as $key => $pw): ?>
                            <option value="<?php echo $key; ?>"><?php echo \Security::htmlentities($pw['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <span class="badge" data-bind="text: $parent.getWeight($index()) + ' DMG',
                              css: { 'bg-success': $parent.autoBalance(), 'bg-warning text-dark': !$parent.autoBalance() },
                              visible: title().trim() !== ''"></span>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100"
                                data-bind="click: $parent.removeRow">
                            削除
                        </button>
                    </div>
                </div>
            </div>

            <!-- 追加ボタン（モバイル用） -->
            <div class="d-grid mb-4 d-md-none">
                <button type="button" class="btn btn-outline-light" data-bind="click: addRow">
                    + 行を追加
                </button>
            </div>

            <!-- 送信ボタン -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="btn btn-outline-light">
                    キャンセル
                </a>
                <div>
                    <span class="text-light me-3" data-bind="visible: validTaskCount() > 0">
                        <span data-bind="text: validTaskCount"></span>件の攻撃
                        （完了: <span data-bind="text: completedTaskCount"></span>件）
                    </span>
                    <button type="submit" class="btn btn-quest btn-lg">
                        保存する
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    /* ダメージマッピングバー */
    .mapping-bar-container {
        position: relative;
        height: 30px;
    }

    .mapping-bar-bg {
        position: absolute;
        width: 100%;
        height: 100%;
        background: #333;
        border-radius: 8px;
        overflow: hidden;
    }

    .mapping-bar {
        position: absolute;
        height: 100%;
        transition: width 0.3s ease, left 0.3s ease;
    }

    .mapping-bar.completed-damage {
        background: linear-gradient(90deg, #10b981, #34d399);
        left: 0;
    }

    .mapping-bar.pending-damage {
        background: linear-gradient(90deg, #6366f1, #818cf8);
    }

    .hp-limit-line {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: 3px;
        background: #ef4444;
    }

    /* 凡例 */
    .legend-box {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 2px;
        margin-right: 4px;
        vertical-align: middle;
    }

    .legend-box.completed {
        background: #10b981;
    }

    .legend-box.pending {
        background: #6366f1;
    }

    .legend-box.total {
        background: linear-gradient(90deg, #10b981 50%, #6366f1 50%);
    }

    /* タスク行 */
    .task-row {
        animation: fadeIn 0.2s ease;
    }

    .task-row.task-completed-row {
        opacity: 0.7;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-control::placeholder {
        color: #6b7280;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ko === 'undefined') {
        console.error('Knockout.js が読み込まれていません。');
        return;
    }

    var bossHp = <?php echo (int)$boss['boss_hp']; ?>;
    var weightMap = {
        <?php foreach ($psychological_weights as $key => $pw): ?>
        '<?php echo $key; ?>': <?php echo (int)$pw['weight']; ?>,
        <?php endforeach; ?>
    };

    // 既存タスクデータ
    var existingTasks = [
        <?php $children = isset($boss['children']) && is_array($boss['children']) ? $boss['children'] : array(); ?>
        <?php foreach ($children as $i => $child): ?>
        {
            title: '<?php echo addslashes($child['title']); ?>',
            weight: '<?php echo array_search($child['weight'], array_column($psychological_weights, 'weight', null)) ?: 'normal'; ?>',
            done: <?php echo ($child['done'] == 1) ? 'true' : 'false'; ?>
        }<?php echo ($i < count($children) - 1) ? ',' : ''; ?>
        <?php endforeach; ?>
    ];

    // 心理的重みのキーを逆引き
    function getWeightKey(weightValue) {
        for (var key in weightMap) {
            if (weightMap[key] == weightValue) {
                return key;
            }
        }
        return 'normal';
    }

    // 既存タスクのweight値からキーを設定
    <?php foreach ($children as $i => $child): ?>
    existingTasks[<?php echo $i; ?>].weight = getWeightKey(<?php echo (int)$child['weight']; ?>);
    <?php endforeach; ?>

    function EditAttacksViewModel() {
        var self = this;

        self.autoBalance = ko.observable(true);

        // タスク行の配列
        self.tasks = ko.observableArray([]);

        // 既存タスクを読み込み
        existingTasks.forEach(function(t) {
            self.tasks.push({
                title: ko.observable(t.title),
                weight: ko.observable(t.weight),
                done: ko.observable(t.done)
            });
        });

        // タスクが0件の場合は空行を追加
        if (self.tasks().length === 0) {
            self.tasks.push({
                title: ko.observable(''),
                weight: ko.observable('normal'),
                done: ko.observable(false)
            });
        }

        // 行を追加
        self.addRow = function() {
            self.tasks.push({
                title: ko.observable(''),
                weight: ko.observable('normal'),
                done: ko.observable(false)
            });
        };

        // 行を削除
        self.removeRow = function(task) {
            self.tasks.remove(task);
            if (self.tasks().length === 0) {
                self.addRow();
            }
        };

        // 有効なタスクを取得
        self.validTasks = ko.computed(function() {
            return ko.utils.arrayFilter(self.tasks(), function(task) {
                return task.title() && task.title().trim() !== '';
            });
        });

        // 有効なタスク数
        self.validTaskCount = ko.computed(function() {
            return self.validTasks().length;
        });

        // 完了タスク数
        self.completedTaskCount = ko.computed(function() {
            var count = 0;
            ko.utils.arrayForEach(self.validTasks(), function(task) {
                if (task.done()) count++;
            });
            return count;
        });

        // 心理的重みの合計比率
        self.totalWeightRatio = ko.computed(function() {
            var total = 0;
            ko.utils.arrayForEach(self.validTasks(), function(task) {
                total += weightMap[task.weight()] || 15;
            });
            return total;
        });

        // 各タスクのweightを取得
        self.getWeight = function(index) {
            var task = self.tasks()[index];
            if (!task || !task.title() || task.title().trim() === '') return 0;

            if (self.autoBalance()) {
                // 自動バランスモード
                var validList = self.validTasks();
                var totalRatio = self.totalWeightRatio();
                if (totalRatio === 0) return 0;

                var validIndex = validList.indexOf(task);
                if (validIndex === -1) return 0;

                var ratio = weightMap[task.weight()] || 15;
                var baseWeight = Math.floor(bossHp * ratio / totalRatio);

                // 端数計算
                var floorSum = 0;
                ko.utils.arrayForEach(validList, function(t) {
                    var r = weightMap[t.weight()] || 15;
                    floorSum += Math.floor(bossHp * r / totalRatio);
                });
                var remainder = bossHp - floorSum;

                if (validIndex === 0 && remainder > 0) {
                    baseWeight += remainder;
                }

                return Math.max(1, baseWeight);
            } else {
                // 手動モード
                return weightMap[task.weight()] || 15;
            }
        };

        // 完了タスクのダメージ合計
        self.completedDamage = ko.computed(function() {
            var total = 0;
            var validList = self.validTasks();
            validList.forEach(function(task, i) {
                if (task.done()) {
                    // 該当タスクのindexを取得
                    var originalIndex = self.tasks().indexOf(task);
                    total += self.getWeight(originalIndex);
                }
            });
            return total;
        });

        // 未完了タスクのダメージ合計
        self.pendingDamage = ko.computed(function() {
            var total = 0;
            var validList = self.validTasks();
            validList.forEach(function(task) {
                if (!task.done()) {
                    var originalIndex = self.tasks().indexOf(task);
                    total += self.getWeight(originalIndex);
                }
            });
            return total;
        });

        // 合計ダメージ
        self.totalDamage = ko.computed(function() {
            return self.completedDamage() + self.pendingDamage();
        });

        // 完了ダメージの割合（%）
        self.completedDamagePercent = ko.computed(function() {
            return Math.min(100, (self.completedDamage() / bossHp) * 100);
        });

        // 未完了ダメージの割合（%）
        self.pendingDamagePercent = ko.computed(function() {
            var remaining = 100 - self.completedDamagePercent();
            var pendingPercent = (self.pendingDamage() / bossHp) * 100;
            return Math.min(remaining, pendingPercent);
        });
    }

    var viewModel = new EditAttacksViewModel();
    ko.applyBindings(viewModel, document.getElementById('damage-mapping'));
    ko.applyBindings(viewModel, document.getElementById('edit-attacks-card'));

    // 二重送信防止
    var isSubmitting = false;
    document.getElementById('editAttacksForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        isSubmitting = true;
    });
});
</script>
