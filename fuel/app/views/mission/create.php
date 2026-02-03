<!--
    Quest-Logic ボス作成画面

    【Phase 5: UX向上】
    ボス作成時に攻撃も一緒に登録できます。
    自動バランスモードで攻撃力を自動配分。
-->

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard'); ?>" class="text-light">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard/project/' . $project['id']); ?>" class="text-light"><?php echo \Security::htmlentities($project['title']); ?></a></li>
                <li class="breadcrumb-item active" style="color: #a5b4fc;">新しいボスを追加</li>
            </ol>
        </nav>
    </div>
</div>

<?php if ( ! empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?>
        <li><?php echo \Security::htmlentities($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?php echo \Uri::create('mission/create/' . $project['id']); ?>" method="POST" id="createBossForm">
    <?php echo \Form::csrf(); ?>

    <!-- ボス情報カード -->
    <div class="card card-quest mb-4">
        <div class="card-header">
            <h4 class="mb-0 text-danger">ボス情報</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="title" class="form-label text-light">ボス名（ミッション名）</label>
                    <input type="text"
                           class="form-control bg-dark text-light border-secondary"
                           id="title"
                           name="title"
                           value="<?php echo \Security::htmlentities($input['title']); ?>"
                           placeholder="例: トップページデザイン"
                           required
                           autofocus>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="boss_rank" class="form-label text-light">ボスランク</label>
                    <select class="form-select bg-dark text-light border-secondary"
                            id="boss_rank"
                            name="boss_rank"
                            data-bind="value: selectedRank">
                        <?php foreach ($boss_ranks as $rank => $info): ?>
                        <option value="<?php echo $rank; ?>"
                                <?php echo $input['boss_rank'] == $rank ? 'selected' : ''; ?>>
                            <?php echo \Security::htmlentities($info['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="deadline" class="form-label text-light">期限（任意）</label>
                    <input type="date"
                           class="form-control bg-dark text-light border-secondary"
                           id="deadline"
                           name="deadline"
                           value="<?php echo \Security::htmlentities($input['deadline']); ?>">
                </div>
            </div>
            <div class="text-light small">
                <span class="badge bg-secondary" data-bind="text: 'HP: ' + bossHp()"></span>
                <span class="text-muted ms-2">ランクが高いほどHPが増加し、討伐時の報酬XPも増えます</span>
            </div>
        </div>
    </div>

    <!-- 攻撃一覧カード -->
    <div class="card card-quest mb-4" id="attacks-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">攻撃を登録（任意）</h5>
            <button type="button" class="btn btn-outline-light btn-sm" data-bind="click: addRow">
                + 行を追加
            </button>
        </div>
        <div class="card-body">
            <!-- 自動バランスモード -->
            <div class="alert alert-primary mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoBalanceSwitch" name="auto_balance" value="1"
                           data-bind="checked: autoBalance" checked>
                    <label class="form-check-label" for="autoBalanceSwitch">
                        <strong>自動バランスモード</strong>（推奨）
                    </label>
                </div>
                <div class="small mt-2" data-bind="visible: autoBalance">
                    心理的重みの<strong>比率</strong>でボスHP（<span data-bind="text: bossHp"></span>）を分配します。
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
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- タスク入力行 -->
            <div data-bind="foreach: tasks">
                <div class="row mb-3 task-row">
                    <div class="col-md-6">
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               data-bind="value: title, attr: { name: 'tasks[' + $index() + '][title]' }"
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
                                data-bind="click: $parent.removeRow, visible: $parent.tasks().length > 1">
                            削除
                        </button>
                    </div>
                </div>
            </div>

            <!-- 追加ボタン（モバイル用） -->
            <div class="d-grid mb-3 d-md-none">
                <button type="button" class="btn btn-outline-light" data-bind="click: addRow">
                    + 行を追加
                </button>
            </div>

            <!-- サマリー表示 -->
            <div class="text-light small" data-bind="visible: validTaskCount() > 0">
                <span data-bind="text: validTaskCount"></span>件の攻撃 |
                合計ダメージ: <span class="fw-bold" data-bind="text: totalDamage"></span> / <span data-bind="text: bossHp"></span> HP
                <span class="badge bg-success ms-2" data-bind="visible: autoBalance()">討伐可能</span>
            </div>
        </div>
    </div>

    <!-- 送信ボタン -->
    <div class="d-flex justify-content-between align-items-center">
        <a href="<?php echo \Uri::create('dashboard/project/' . $project['id']); ?>" class="btn btn-outline-light">
            キャンセル
        </a>
        <button type="submit" class="btn btn-quest btn-lg">
            ボスを出現させる
        </button>
    </div>
</form>

<style>
    .task-row {
        animation: fadeIn 0.2s ease;
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

    var baseHp = <?php echo (int)\Config::get('quest.base_boss_hp', 100); ?>;
    var rankMultipliers = {
        <?php foreach ($boss_ranks as $rank_id => $rank_info): ?>
        '<?php echo $rank_id; ?>': <?php echo $rank_info['hp_multiplier']; ?>,
        <?php endforeach; ?>
    };
    var weightMap = {
        <?php foreach ($psychological_weights as $key => $pw): ?>
        '<?php echo $key; ?>': <?php echo (int)$pw['weight']; ?>,
        <?php endforeach; ?>
    };

    function CreateBossViewModel() {
        var self = this;

        self.selectedRank = ko.observable('<?php echo $input['boss_rank']; ?>');
        self.autoBalance = ko.observable(true);

        // ボスHP計算
        self.bossHp = ko.computed(function() {
            var multiplier = rankMultipliers[self.selectedRank()] || 1;
            return Math.floor(baseHp * multiplier);
        });

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
                var validList = self.validTasks();
                var totalRatio = self.totalWeightRatio();
                var hp = self.bossHp();
                if (totalRatio === 0) return 0;

                var validIndex = validList.indexOf(task);
                if (validIndex === -1) return 0;

                var ratio = weightMap[task.weight()] || 15;
                var baseWeight = Math.floor(hp * ratio / totalRatio);

                // 端数計算
                var floorSum = 0;
                ko.utils.arrayForEach(validList, function(t) {
                    var r = weightMap[t.weight()] || 15;
                    floorSum += Math.floor(hp * r / totalRatio);
                });
                var remainder = hp - floorSum;

                if (validIndex === 0 && remainder > 0) {
                    baseWeight += remainder;
                }

                return Math.max(1, baseWeight);
            } else {
                return weightMap[task.weight()] || 15;
            }
        };

        // 合計ダメージ
        self.totalDamage = ko.computed(function() {
            if (self.autoBalance()) {
                return self.validTaskCount() > 0 ? self.bossHp() : 0;
            } else {
                var total = 0;
                ko.utils.arrayForEach(self.validTasks(), function(task) {
                    total += weightMap[task.weight()] || 15;
                });
                return total;
            }
        });
    }

    var viewModel = new CreateBossViewModel();
    ko.applyBindings(viewModel, document.getElementById('createBossForm'));

    // 二重送信防止
    var isSubmitting = false;
    document.getElementById('createBossForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        isSubmitting = true;
    });
});
</script>
