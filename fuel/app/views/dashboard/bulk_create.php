<!--
    Quest-Logic プロジェクト一斉登録画面

    【Phase 5: UX向上】
    プロジェクト内のボスと攻撃を一度に登録できます。
    自動バランスモードで、各ボスのHPを攻撃に比率分配します。
-->

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard'); ?>" class="text-light">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo \Uri::create('dashboard/project/' . $project['id']); ?>" class="text-light"><?php echo \Security::htmlentities($project['title']); ?></a></li>
                <li class="breadcrumb-item active" style="color: #a5b4fc;">一斉登録</li>
            </ol>
        </nav>
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
<form action="<?php echo \Uri::create('dashboard/bulk_create/' . $project['id']); ?>" method="POST" id="bulkCreateForm">
    <?php echo \Form::csrf(); ?>

    <!-- 自動バランスモード -->
    <div class="card card-quest mb-4">
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="autoBalanceSwitch" name="auto_balance" value="1"
                       data-bind="checked: autoBalance" checked>
                <label class="form-check-label text-light" for="autoBalanceSwitch">
                    <strong>自動バランスモード</strong>（推奨）
                </label>
            </div>
            <div class="small text-light mt-2" data-bind="visible: autoBalance">
                各ボスのHPを、攻撃の心理的重みの<strong>比率</strong>で分配します。<br>
                全ての攻撃を完了するとボスを討伐できます。
            </div>
            <div class="small text-muted mt-2" data-bind="visible: !autoBalance()">
                心理的重みの値をそのまま攻撃力として使用します。
            </div>
        </div>
    </div>

    <!-- ボス一覧 -->
    <div id="bosses-container" data-bind="foreach: bosses">
        <div class="card card-quest mb-4 boss-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center flex-grow-1">
                    <span class="badge bg-danger me-2" data-bind="text: 'BOSS ' + ($index() + 1)"></span>
                    <input type="text"
                           class="form-control bg-dark text-light border-secondary"
                           data-bind="value: title, attr: { name: 'bosses[' + $index() + '][title]' }"
                           placeholder="ボス名を入力"
                           style="max-width: 300px;">
                    <select class="form-select bg-dark text-light border-secondary ms-2"
                            data-bind="value: rank, attr: { name: 'bosses[' + $index() + '][rank]' }"
                            style="max-width: 150px;">
                        <?php foreach ($boss_ranks as $rank_id => $rank_info): ?>
                        <option value="<?php echo $rank_id; ?>"><?php echo \Security::htmlentities($rank_info['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="badge bg-secondary ms-2" data-bind="text: 'HP: ' + getHp()"></span>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm ms-2"
                        data-bind="click: $parent.removeBoss, visible: $parent.bosses().length > 1">
                    削除
                </button>
            </div>
            <div class="card-body">
                <!-- 攻撃一覧 -->
                <div class="mb-3" data-bind="foreach: attacks">
                    <div class="row mb-2 attack-row">
                        <div class="col-md-6">
                            <input type="text"
                                   class="form-control bg-dark text-light border-secondary"
                                   data-bind="value: title, attr: { name: 'bosses[' + $parentContext.$index() + '][attacks][' + $index() + '][title]' }"
                                   placeholder="攻撃名を入力">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select bg-dark text-light border-secondary"
                                    data-bind="value: weight, attr: { name: 'bosses[' + $parentContext.$index() + '][attacks][' + $index() + '][weight]' }">
                                <?php foreach ($psychological_weights as $key => $pw): ?>
                                <option value="<?php echo $key; ?>"><?php echo \Security::htmlentities($pw['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <!-- 自動バランス時のDMG表示 -->
                            <span class="badge bg-success me-2"
                                  data-bind="visible: $root.autoBalance() && title().trim() !== '', text: $parent.getAttackDmg($index()) + ' DMG'"></span>
                            <!-- 手動モード時のDMG表示 -->
                            <span class="badge bg-warning text-dark me-2"
                                  data-bind="visible: !$root.autoBalance() && title().trim() !== '', text: $parent.getManualDmg($index()) + ' DMG'"></span>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100"
                                    data-bind="click: $parent.removeAttack, visible: $parent.attacks().length > 1">
                                &times;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 攻撃追加ボタン -->
                <button type="button" class="btn btn-outline-light btn-sm"
                        data-bind="click: addAttack">
                    + 攻撃を追加
                </button>

                <!-- ボスの合計ダメージ表示 -->
                <div class="mt-3 text-light small" data-bind="visible: validAttackCount() > 0">
                    <span data-bind="text: validAttackCount"></span>件の攻撃 |
                    合計ダメージ: <span class="fw-bold" data-bind="text: $root.autoBalance() ? getHp() : getTotalManualDmg()"></span> / <span data-bind="text: getHp()"></span> HP
                    <span class="badge bg-success ms-2" data-bind="visible: $root.autoBalance()">討伐可能</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ボス追加ボタン -->
    <div class="mb-4">
        <button type="button" class="btn btn-outline-danger" data-bind="click: addBoss">
            + ボスを追加
        </button>
    </div>

    <!-- 送信ボタン -->
    <div class="d-flex justify-content-between align-items-center">
        <a href="<?php echo \Uri::create('dashboard/project/' . $project['id']); ?>" class="btn btn-outline-light">
            キャンセル
        </a>
        <div>
            <span class="text-light me-3" data-bind="visible: validBossCount() > 0">
                <span data-bind="text: validBossCount"></span>体のボス、
                <span data-bind="text: totalAttackCount"></span>件の攻撃
            </span>
            <button type="submit" class="btn btn-quest btn-lg" data-bind="enable: validBossCount() > 0">
                一斉登録
            </button>
        </div>
    </div>
</form>

<style>
    .boss-card {
        border-left: 4px solid #ef4444;
    }

    .attack-row {
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
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

    // HP計算用の設定
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

    // 攻撃モデル
    function AttackModel(parent) {
        var self = this;
        self.parent = parent;
        self.title = ko.observable('');
        self.weight = ko.observable('normal');
    }

    // ボスモデル
    function BossModel(root) {
        var self = this;
        self.root = root;
        self.title = ko.observable('');
        self.rank = ko.observable('1');
        self.attacks = ko.observableArray([
            new AttackModel(self),
            new AttackModel(self),
            new AttackModel(self)
        ]);

        // HP計算
        self.getHp = ko.computed(function() {
            var multiplier = rankMultipliers[self.rank()] || 1;
            return Math.floor(baseHp * multiplier);
        });

        // 有効な攻撃を取得
        self.validAttacks = ko.computed(function() {
            return ko.utils.arrayFilter(self.attacks(), function(a) {
                return a.title() && a.title().trim() !== '';
            });
        });

        // 有効な攻撃数
        self.validAttackCount = ko.computed(function() {
            return self.validAttacks().length;
        });

        // 心理的重みの合計比率
        self.totalWeightRatio = ko.computed(function() {
            var total = 0;
            ko.utils.arrayForEach(self.validAttacks(), function(a) {
                total += weightMap[a.weight()] || 15;
            });
            return total;
        });

        // 自動バランス時の各攻撃のDMG
        self.getAttackDmg = function(index) {
            var validList = self.validAttacks();
            var hp = self.getHp();
            var totalRatio = self.totalWeightRatio();

            if (totalRatio === 0 || validList.length === 0) return 0;

            // この攻撃が有効リストの何番目か
            var attack = self.attacks()[index];
            var validIndex = -1;
            for (var i = 0; i < validList.length; i++) {
                if (validList[i] === attack) {
                    validIndex = i;
                    break;
                }
            }
            if (validIndex === -1) return 0;

            var ratio = weightMap[attack.weight()] || 15;
            var baseWeight = Math.floor(hp * ratio / totalRatio);

            // 端数計算
            var floorSum = 0;
            ko.utils.arrayForEach(validList, function(a) {
                var r = weightMap[a.weight()] || 15;
                floorSum += Math.floor(hp * r / totalRatio);
            });
            var remainder = hp - floorSum;

            // 最初の有効攻撃に端数を加算
            if (validIndex === 0 && remainder > 0) {
                baseWeight += remainder;
            }

            return Math.max(1, baseWeight);
        };

        // 手動モード時のDMG
        self.getManualDmg = function(index) {
            var attack = self.attacks()[index];
            return weightMap[attack.weight()] || 15;
        };

        // 手動モード時の合計DMG
        self.getTotalManualDmg = ko.computed(function() {
            var total = 0;
            ko.utils.arrayForEach(self.validAttacks(), function(a) {
                total += weightMap[a.weight()] || 15;
            });
            return total;
        });

        // 攻撃を追加
        self.addAttack = function() {
            self.attacks.push(new AttackModel(self));
        };

        // 攻撃を削除
        self.removeAttack = function(attack) {
            self.attacks.remove(attack);
        };
    }

    // メインViewModel
    function BulkCreateViewModel() {
        var self = this;

        self.autoBalance = ko.observable(true);

        self.bosses = ko.observableArray([
            new BossModel(self)
        ]);

        // 有効なボス数
        self.validBossCount = ko.computed(function() {
            var count = 0;
            ko.utils.arrayForEach(self.bosses(), function(b) {
                if (b.title() && b.title().trim() !== '') {
                    count++;
                }
            });
            return count;
        });

        // 全攻撃数
        self.totalAttackCount = ko.computed(function() {
            var count = 0;
            ko.utils.arrayForEach(self.bosses(), function(b) {
                if (b.title() && b.title().trim() !== '') {
                    count += b.validAttackCount();
                }
            });
            return count;
        });

        // ボスを追加
        self.addBoss = function() {
            self.bosses.push(new BossModel(self));
        };

        // ボスを削除
        self.removeBoss = function(boss) {
            self.bosses.remove(boss);
        };
    }

    var viewModel = new BulkCreateViewModel();
    ko.applyBindings(viewModel, document.getElementById('bulkCreateForm'));

    // 二重送信防止
    var isSubmitting = false;
    document.getElementById('bulkCreateForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        if (viewModel.validBossCount() === 0) {
            e.preventDefault();
            alert('少なくとも1つのボスを入力してください。');
            return false;
        }
        isSubmitting = true;
    });
});
</script>
