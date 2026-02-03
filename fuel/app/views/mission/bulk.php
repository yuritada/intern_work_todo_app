<!--
    Quest-Logic タスク一斉登録画面

    【Phase 5: UX向上 - 一斉登録 & オーバーキル防止】
    - テキストベースで高速にタスクを追加
    - 「心理的重み」選択により適切なweightを自動設定
    - Knockout.js でリアルタイムに合計ダメージを計算
    - ボスのHPを超過する場合は警告を表示
-->

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard'); ?>" class="text-light">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard/project/' . $boss['project_id']); ?>" class="text-light"><?php echo \Security::htmlentities($boss['project_title']); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="text-light"><?php echo \Security::htmlentities($boss['title']); ?></a></li>
                <li class="breadcrumb-item active" style="color: #a5b4fc;">一斉登録</li>
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
        </h4>
    </div>
    <div class="card-body">
        <!-- ダメージマッピングバー -->
        <div class="damage-mapping-section mb-4" id="damage-mapping">
            <h5 class="text-light mb-3">ダメージ配分マッピング</h5>

            <!-- HPバー（Knockout.jsでバインド） -->
            <div class="mapping-bar-container mb-2">
                <div class="mapping-bar-bg">
                    <!-- 既存タスクのダメージ -->
                    <div class="mapping-bar existing-damage"
                         data-bind="style: { width: existingDamagePercent() + '%' }">
                    </div>
                    <!-- 新規タスクのダメージ -->
                    <div class="mapping-bar new-damage"
                         data-bind="style: { width: newDamagePercent() + '%', left: existingDamagePercent() + '%' }">
                    </div>
                </div>
                <!-- HP上限ライン -->
                <div class="hp-limit-line"></div>
            </div>

            <!-- 凡例 -->
            <div class="d-flex justify-content-between flex-wrap text-light small mb-3">
                <div>
                    <span class="legend-box existing"></span>
                    登録済み: <span data-bind="text: existingDamage"><?php echo $current_total_weight; ?></span> DMG
                </div>
                <div>
                    <span class="legend-box new"></span>
                    新規追加: <span data-bind="text: newDamage">0</span> DMG
                </div>
                <div>
                    <span class="legend-box total"></span>
                    合計: <span data-bind="text: totalDamage"><?php echo $current_total_weight; ?></span> / <?php echo $boss['boss_hp']; ?> HP
                </div>
            </div>

            <!-- オーバーキル警告 -->
            <div class="alert alert-warning d-none" id="overkill-alert" data-bind="css: { 'd-none': !isOverkill() }">
                <strong>Overkill Alert!</strong>
                合計ダメージがボスのHP（<?php echo $boss['boss_hp']; ?>）を超えています。
                タスクを減らすか、難易度を下げることを検討してください。
            </div>

            <!-- HP不足警告（ダメージが足りない場合） -->
            <div class="alert alert-info d-none" data-bind="css: { 'd-none': !isUnderkill() }, visible: newDamage() > 0">
                <strong>Tip:</strong>
                現在の合計ダメージではボスを倒しきれません。
                残り <span data-bind="text: remainingHp"></span> HP分のタスクが必要です。
            </div>
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

<!-- 一斉登録フォーム -->
<div class="card card-quest">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">攻撃を一斉登録</h5>
        <button type="button" class="btn btn-outline-light btn-sm" data-bind="click: addRow">
            + 行を追加
        </button>
    </div>
    <div class="card-body">
        <form action="<?php echo \Uri::create('mission/bulk/' . $boss['id']); ?>" method="POST" id="bulkForm">
            <?php echo \Form::csrf(); ?>

            <!-- 自動バランスモード切替 -->
            <div class="alert alert-primary mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoBalanceSwitch" name="auto_balance" value="1"
                           data-bind="checked: autoBalance">
                    <label class="form-check-label" for="autoBalanceSwitch">
                        <strong>自動バランスモード</strong>（推奨）
                    </label>
                </div>
                <div class="small mt-2" data-bind="visible: autoBalance">
                    心理的重みの<strong>比率</strong>で残りHP（<span data-bind="text: remainingHpForBalance"></span>）を分配します。<br>
                    全タスクを完了するとボスを討伐できます。
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

            <!-- 自動バランス時の配分表示 -->
            <div class="alert alert-success mb-4" data-bind="visible: autoBalance() && validTaskCount() > 0">
                <strong>比率分配:</strong>
                選択した心理的重みの比率で、残り <span data-bind="text: remainingHpForBalance"></span> HP を分配します。
                <span data-bind="visible: autoBalanceRemainder() > 0">
                    （端数 <span data-bind="text: autoBalanceRemainder"></span> DMG は最初のタスクに加算）
                </span>
            </div>

            <!-- タスク入力行（Knockout.js foreach） -->
            <div id="task-rows" data-bind="foreach: tasks">
                <div class="row mb-3 task-row">
                    <div class="col-md-6">
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               data-bind="value: title, attr: { name: 'titles[' + $index() + ']' }"
                               placeholder="タスク名を入力">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select bg-dark text-light border-secondary"
                                data-bind="value: weight, attr: { name: 'weights[' + $index() + ']' }">
                            <?php foreach ($psychological_weights as $key => $pw): ?>
                            <option value="<?php echo $key; ?>"><?php echo \Security::htmlentities($pw['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- 自動バランス時: 計算されたDMGを表示 -->
                    <div class="col-md-1 d-flex align-items-center" data-bind="visible: $parent.autoBalance() && title().trim() !== ''">
                        <span class="badge bg-success" data-bind="text: $parent.getAutoWeight($index()) + ' DMG'"></span>
                    </div>
                    <!-- 手動モード時: 固定DMGを表示 -->
                    <div class="col-md-1 d-flex align-items-center" data-bind="visible: !$parent.autoBalance() && title().trim() !== ''">
                        <span class="badge bg-warning text-dark" data-bind="text: $parent.getManualWeight($index()) + ' DMG'"></span>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100"
                                data-bind="click: $parent.removeRow, visible: $parent.tasks().length > 1">
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
                        <span data-bind="text: validTaskCount"></span>件のタスクを追加
                    </span>
                    <button type="submit" class="btn btn-quest btn-lg"
                            data-bind="enable: validTaskCount() > 0, css: { 'btn-warning': isOverkill() }">
                        <span data-bind="text: isOverkill() ? '警告を無視して登録' : '一斉登録'"></span>
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
        height: 40px;
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

    .mapping-bar.existing-damage {
        background: linear-gradient(90deg, #6366f1, #818cf8);
        left: 0;
    }

    .mapping-bar.new-damage {
        background: linear-gradient(90deg, #10b981, #34d399);
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

    .legend-box.existing {
        background: #6366f1;
    }

    .legend-box.new {
        background: #10b981;
    }

    .legend-box.total {
        background: linear-gradient(90deg, #6366f1 50%, #10b981 50%);
    }

    /* タスク行 */
    .task-row {
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* フォーム要素 */
    .form-control::placeholder {
        color: #6b7280;
    }

    /* オーバーキル時のボタン */
    .btn-quest.btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Knockout.js 存在チェック
    if (typeof ko === 'undefined') {
        console.error('Knockout.js が読み込まれていません。');
        return;
    }

    // 設定値
    var bossHp = <?php echo (int)$boss['boss_hp']; ?>;
    var existingDamage = <?php echo (int)$current_total_weight; ?>;
    var weightMap = {
        <?php foreach ($psychological_weights as $key => $pw): ?>
        '<?php echo $key; ?>': <?php echo (int)$pw['weight']; ?>,
        <?php endforeach; ?>
    };

    // ViewModel
    function BulkViewModel() {
        var self = this;

        // 自動バランスモード
        self.autoBalance = ko.observable(true); // デフォルトでON

        // タスク行の配列
        self.tasks = ko.observableArray([
            { title: ko.observable(''), weight: ko.observable('normal') },
            { title: ko.observable(''), weight: ko.observable('normal') },
            { title: ko.observable(''), weight: ko.observable('normal') }
        ]);

        // 行を追加
        self.addRow = function() {
            self.tasks.push({
                title: ko.observable(''),
                weight: ko.observable('normal')
            });
        };

        // 行を削除
        self.removeRow = function(task) {
            self.tasks.remove(task);
        };

        // 既存ダメージ（固定値）
        self.existingDamage = ko.observable(existingDamage);

        // 有効なタスク（タイトルが入力されているもの）を取得
        self.validTasks = ko.computed(function() {
            var valid = [];
            ko.utils.arrayForEach(self.tasks(), function(task, index) {
                if (task.title() && task.title().trim() !== '') {
                    valid.push({ task: task, index: index });
                }
            });
            return valid;
        });

        // 有効なタスク数
        self.validTaskCount = ko.computed(function() {
            return self.validTasks().length;
        });

        // 自動バランス用: 残りHP
        self.remainingHpForBalance = ko.computed(function() {
            return Math.max(0, bossHp - self.existingDamage());
        });

        // 有効タスクの心理的重みの合計（比率の分母）
        self.totalWeightRatio = ko.computed(function() {
            var total = 0;
            ko.utils.arrayForEach(self.validTasks(), function(item) {
                var w = weightMap[item.task.weight()] || 15;
                total += w;
            });
            return total;
        });

        // 自動バランス用: 端数を計算（小数点以下の累積）
        self.autoBalanceRemainder = ko.computed(function() {
            if (self.validTaskCount() === 0) return 0;
            var remaining = self.remainingHpForBalance();
            var totalRatio = self.totalWeightRatio();
            if (totalRatio === 0) return 0;

            // 各タスクのfloor値の合計を計算
            var floorSum = 0;
            ko.utils.arrayForEach(self.validTasks(), function(item) {
                var ratio = weightMap[item.task.weight()] || 15;
                floorSum += Math.floor(remaining * ratio / totalRatio);
            });
            return remaining - floorSum;
        });

        // 自動バランス用: 各タスクのweightを取得（比率分配）
        self.getAutoWeight = function(index) {
            var validTasks = self.validTasks();
            var remaining = self.remainingHpForBalance();
            var totalRatio = self.totalWeightRatio();

            if (totalRatio === 0 || validTasks.length === 0) return 0;

            // このタスクが有効タスクリストの何番目か
            var validIndex = -1;
            for (var i = 0; i < validTasks.length; i++) {
                if (validTasks[i].index === index) {
                    validIndex = i;
                    break;
                }
            }
            if (validIndex === -1) return 0;

            var task = self.tasks()[index];
            var ratio = weightMap[task.weight()] || 15;
            var baseWeight = Math.floor(remaining * ratio / totalRatio);

            // 最初の有効タスクに端数を加算
            if (validIndex === 0) {
                baseWeight += self.autoBalanceRemainder();
            }

            return Math.max(1, baseWeight); // 最低1ダメージ
        };

        // 手動モード用: 固定weightを取得
        self.getManualWeight = function(index) {
            var task = self.tasks()[index];
            return weightMap[task.weight()] || 15;
        };

        // 新規タスクの合計ダメージを計算
        self.newDamage = ko.computed(function() {
            if (self.autoBalance()) {
                // 自動バランスモード: 残りHP分を追加
                var count = self.validTaskCount();
                if (count === 0) return 0;
                return self.remainingHpForBalance();
            } else {
                // 手動モード: 選択されたweightを合計
                var total = 0;
                ko.utils.arrayForEach(self.tasks(), function(task) {
                    if (task.title() && task.title().trim() !== '') {
                        var w = weightMap[task.weight()] || 15;
                        total += w;
                    }
                });
                return total;
            }
        });

        // 合計ダメージ
        self.totalDamage = ko.computed(function() {
            return self.existingDamage() + self.newDamage();
        });

        // 既存ダメージの割合（%）
        self.existingDamagePercent = ko.computed(function() {
            return Math.min(100, (self.existingDamage() / bossHp) * 100);
        });

        // 新規ダメージの割合（%）
        self.newDamagePercent = ko.computed(function() {
            var remaining = 100 - self.existingDamagePercent();
            var newPercent = (self.newDamage() / bossHp) * 100;
            return Math.min(remaining, newPercent);
        });

        // オーバーキル判定
        self.isOverkill = ko.computed(function() {
            if (self.autoBalance()) {
                return false; // 自動バランスではオーバーキルは発生しない
            }
            return self.totalDamage() > bossHp;
        });

        // アンダーキル判定（ダメージ不足）
        self.isUnderkill = ko.computed(function() {
            if (self.autoBalance()) {
                return false; // 自動バランスではアンダーキルは発生しない
            }
            return self.totalDamage() < bossHp && self.newDamage() > 0;
        });

        // 残りHP
        self.remainingHp = ko.computed(function() {
            return Math.max(0, bossHp - self.totalDamage());
        });
    }

    // ViewModelをバインド
    var viewModel = new BulkViewModel();
    ko.applyBindings(viewModel, document.getElementById('damage-mapping'));
    ko.applyBindings(viewModel, document.getElementById('bulkForm'));

    // 二重送信防止
    var isSubmitting = false;
    document.getElementById('bulkForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        if (viewModel.validTaskCount() === 0) {
            e.preventDefault();
            alert('少なくとも1つのタスクを入力してください。');
            return false;
        }
        isSubmitting = true;
    });
});
</script>
