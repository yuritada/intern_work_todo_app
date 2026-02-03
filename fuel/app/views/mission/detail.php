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
                <li class="breadcrumb-item active" style="color: #a5b4fc;"><?php echo \Security::htmlentities($boss['title']); ?></li>
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
                <p class="text-secondary mb-0">完了タスク</p>
            </div>
            <div class="col-6">
                <h4 class="text-success" data-bind="text: hpPercent() + '%'">
                    <?php echo $boss['hp_percent']; ?>%
                </h4>
                <p class="text-secondary mb-0">残りHP</p>
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
        <a href="<?php echo \Uri::create('mission/edit_attacks/' . $boss['id']); ?>" class="btn btn-quest btn-sm">
            攻撃を編集
        </a>
    </div>
    <div class="card-body" id="task-list-container">
        <!-- タスクがない場合の表示 -->
        <div class="text-center py-4" data-bind="visible: tasks().length === 0">
            <p class="text-light mb-3">まだ攻撃アクションがありません。</p>
            <a href="<?php echo \Uri::create('mission/edit_attacks/' . $boss['id']); ?>" class="btn btn-quest">
                + 攻撃を追加
            </a>
        </div>

        <!-- タスク一覧 -->
        <div class="task-list" data-bind="visible: tasks().length > 0, foreach: tasks">
            <div class="task-item d-flex align-items-center p-3 mb-2" data-bind="css: { 'task-completed': done() }, attr: { 'data-task-id': id() }">
                <!--
                    【解説: チェックボックスのバインディング】
                    checked: done（双方向）ではなく attr: { checked: done }（一方向）を使用。
                    双方向バインディングだとclick時に自動でdone()がトグルされ、
                    Ajax通信との競合が発生するため、一方向にして手動制御する。
                -->
                <input type="checkbox"
                       class="form-check-input me-3 task-checkbox"
                       data-bind="attr: { checked: done }, click: $parent.toggleTask">
                <div class="flex-grow-1">
                    <span class="task-title" data-bind="text: title, css: { 'text-decoration-line-through': done() }"></span>
                    <span class="badge bg-warning text-dark ms-2" data-bind="text: 'DMG: ' + weight()"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo \Uri::create('dashboard/project/' . $boss['project_id']); ?>" class="btn btn-outline-light">
        プロジェクトに戻る
    </a>
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
        color: #f3f4f6;
        font-size: 1.1rem;
    }

    /* 配色修正: text-secondary を暗い背景用に調整 */
    .text-secondary {
        color: #9ca3af !important;
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

    /* ダメージエフェクト */
    .damage-effect {
        position: fixed;
        font-size: 2rem;
        font-weight: bold;
        color: #02e35c;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        pointer-events: none;
        z-index: 1060;
        animation: damageFloat 1s ease-out forwards;
    }

    @keyframes damageFloat {
        0% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        50% {
            transform: translateY(-30px) scale(1.2);
        }
        100% {
            opacity: 0;
            transform: translateY(-60px) scale(0.8);
        }
    }

    /* HPバー減少アニメーション */
    .hp-damage-flash {
        animation: hpFlash 0.3s ease;
    }

    @keyframes hpFlash {
        0%, 100% { filter: brightness(1); }
        50% { filter: brightness(1.5); }
    }

    /* ボス討伐エフェクト */
    .boss-defeated-effect {
        animation: defeatPulse 0.5s ease;
    }

    @keyframes defeatPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    /* タスク完了チェックアニメーション */
    .task-check-animation {
        animation: checkBounce 0.3s ease;
    }

    @keyframes checkBounce {
        0% { transform: scale(1); }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); }
    }

    /* 勝利オーバーレイ */
    .victory-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 1100;
        animation: fadeIn 0.5s ease;
    }

    .victory-overlay h1 {
        font-size: 4rem;
        color: #fbbf24;
        text-shadow: 0 0 20px rgba(251, 191, 36, 0.5);
        animation: victoryText 1s ease infinite;
    }

    @keyframes victoryText {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* プロジェクト完全制覇エフェクト */
    .project-clear-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 1200;
        animation: fadeIn 0.5s ease;
    }

    .project-clear-overlay h1 {
        font-size: 3rem;
        color: #fbbf24;
        text-shadow: 0 0 30px rgba(251, 191, 36, 0.8);
        animation: projectClearText 1.5s ease infinite;
        text-align: center;
    }

    @keyframes projectClearText {
        0%, 100% { transform: scale(1); text-shadow: 0 0 30px rgba(251, 191, 36, 0.8); }
        50% { transform: scale(1.05); text-shadow: 0 0 50px rgba(251, 191, 36, 1); }
    }

    /* 紙吹雪エフェクト */
    .confetti-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1201;
        overflow: hidden;
    }

    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        top: -10px;
        animation: confettiFall linear forwards;
    }

    @keyframes confettiFall {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }
</style>

<!--
    【解説: Knockout.js ViewModel】
    ページ下部でViewModelを定義し、ko.applyBindings() でHTMLとバインドします。
    observableを使用することで、値の変更が自動的にUIに反映されます。

    【重要: ライブラリ読み込み順序】
    jQuery と Knockout.js は template.php の <head> 内で読み込まれている必要がある。
    このスクリプトは $content として template.php 内で展開されるため、
    <head> 内でライブラリが読み込まれていれば、ここで ko と $ が利用可能。
-->
<script>
// 【即時チェック】ライブラリが読み込まれているか確認（DOMContentLoaded の外側）
(function() {
    if (typeof ko === 'undefined') {
        console.error('[FATAL] Knockout.js が読み込まれていません。template.php の <head> 内で knockout.js を読み込んでください。');
        // フォームのデフォルト動作を無効化（JSクラッシュ対策）
        var form = document.getElementById('addTaskForm');
        if (form) {
            form.onsubmit = function() { return false; };
        }
    }
    if (typeof jQuery === 'undefined' && typeof $ === 'undefined') {
        console.error('[FATAL] jQuery が読み込まれていません。template.php の <head> 内で jquery.js を読み込んでください。');
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // 【解説: ライブラリ存在チェック】
    // Knockout.js と jQuery が正しく読み込まれているか確認
    if (typeof ko === 'undefined') {
        console.error('Knockout.js が読み込まれていません。template.php の script タグを確認してください。');
        document.getElementById('addTaskError').textContent = 'システムエラー: ページを再読み込みしてください。';
        document.getElementById('addTaskError').classList.remove('d-none');
        return;
    }
    if (typeof $ === 'undefined' && typeof jQuery === 'undefined') {
        console.error('jQuery が読み込まれていません。template.php の script タグを確認してください。');
        return;
    }

    // 【解説: CSRFトークン管理】
    // Ajax通信後にトークンが再生成されるため、グローバル変数で管理
    var csrfTokenKey = '<?php echo \Config::get('security.csrf_token_key', 'fuel_csrf_token'); ?>';
    var csrfToken = '<?php echo \Security::fetch_token(); ?>';

    // CSRFトークンを更新するヘルパー関数
    function updateCsrfToken(response) {
        if (response.csrf_token) {
            csrfToken = response.csrf_token;
        }
    }

    // ViewModel定義
    function BattleViewModel() {
        var self = this;

        // Observable データ（値の変更を監視）
        self.currentHp = ko.observable(<?php echo (int)$boss['current_hp']; ?>);
        self.maxHp = ko.observable(<?php echo (int)$boss['boss_hp']; ?>);
        self.completedTasks = ko.observable(<?php echo (int)$boss['completed_tasks']; ?>);
        self.totalTasks = ko.observable(<?php echo (int)$boss['total_tasks']; ?>);
        // 【修正】boolean値を正しく出力（クォートなし）
        self.isDead = ko.observable(<?php echo ($boss['done'] == 1) ? 'true' : 'false'; ?>);
        self.isProcessing = ko.observable(false); // 処理中フラグ

        // タスク配列（observableArray）
        // 【Null安全化】$boss['children']が存在しない場合に備えたフォールバック
        self.tasks = ko.observableArray([
            <?php $children = isset($boss['children']) && is_array($boss['children']) ? $boss['children'] : array(); ?>
            <?php foreach ($children as $i => $child): ?>
            {
                id: ko.observable(<?php echo (int)$child['id']; ?>),
                title: ko.observable('<?php echo addslashes($child['title']); ?>'),
                weight: ko.observable(<?php echo (int)$child['weight']; ?>),
                done: ko.observable(<?php echo ($child['done'] == 1) ? 'true' : 'false'; ?>)
            }<?php echo ($i < count($children) - 1) ? ',' : ''; ?>
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
        self.toggleTask = function(task, event) {
            // 処理中は無視
            if (self.isProcessing()) {
                return false;
            }

            var newDone = !task.done();
            self.isProcessing(true);

            // チェックボックスにアニメーションクラスを追加
            var checkbox = event.target;
            checkbox.classList.add('task-check-animation');

            // 【修正】動的CSRFトークンを使用
            var postData = {
                task_id: task.id()
            };
            postData[csrfTokenKey] = csrfToken;

            // 【解説: Ajax通信】
            // jQueryの$.ajaxでサーバーと非同期通信
            // 成功時にViewModelを更新し、UIが自動的に変更される
            $.ajax({
                url: '<?php echo \Uri::base(); ?>api/battle/' + (newDone ? 'attack' : 'undo'),
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    // CSRFトークンを更新
                    updateCsrfToken(response);

                    if (response.success) {
                        // タスクの状態を更新
                        task.done(newDone);

                        // ダメージエフェクト表示（攻撃時のみ）
                        if (newDone && response.damage) {
                            showDamageEffect(response.damage, event);
                            flashHpBar();
                        }

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
                            showVictoryOverlay(response.gained_xp, response.project_cleared);
                            updateUserStatus(); // ナビバーのXP更新
                        }

                        // レベルアップ時
                        if (response.level_up) {
                            showLevelUpMessage(response.new_level);
                        }
                    } else {
                        showErrorMessage(response.error || '不明なエラー');
                        // チェックボックスを元に戻す
                        task.done(!newDone);
                    }
                },
                error: function(xhr, status, error) {
                    // 【修正】詳細なエラー情報をコンソールに出力
                    console.error('Toggle task error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    showErrorMessage('通信エラーが発生しました。（' + xhr.status + '）');
                    task.done(!newDone);
                },
                complete: function() {
                    self.isProcessing(false);
                    checkbox.classList.remove('task-check-animation');
                }
            });

            // イベントの伝播を止める（チェックボックスのデフォルト動作を防ぐ）
            return false;
        };
    }

    // ViewModelインスタンスを作成（グローバル参照用）
    var viewModel = new BattleViewModel();

    // ViewModelをHTMLにバインド
    var bossView = document.getElementById('boss-view');
    var taskListContainer = document.getElementById('task-list-container');

    if (bossView) {
        ko.applyBindings(viewModel, bossView);
    }
    if (taskListContainer) {
        ko.applyBindings(viewModel, taskListContainer);
    }

    // ダメージエフェクト表示
    function showDamageEffect(damage, event) {
        var damageEl = document.createElement('div');
        damageEl.className = 'damage-effect';
        damageEl.textContent = damage + ' HP';

        // クリック位置の近くに表示
        var rect = event.target.getBoundingClientRect();
        damageEl.style.left = (rect.left + rect.width / 2) + 'px';
        damageEl.style.top = (rect.top - 20) + 'px';

        document.body.appendChild(damageEl);

        // アニメーション後に削除
        setTimeout(function() {
            damageEl.remove();
        }, 1000);
    }

    // HPバーフラッシュ
    function flashHpBar() {
        var hpBar = document.querySelector('.boss-hp-bar');
        if (hpBar) {
            hpBar.classList.add('hp-damage-flash');
            setTimeout(function() {
                hpBar.classList.remove('hp-damage-flash');
            }, 300);
        }
    }

    // 勝利オーバーレイ表示
    function showVictoryOverlay(xp, projectCleared) {
        var overlay = document.createElement('div');
        overlay.className = 'victory-overlay';
        overlay.innerHTML = '<h1>VICTORY!</h1>' +
            '<p style="color: #f3f4f6; font-size: 1.5rem; margin-top: 1rem;">ボスを討伐しました！</p>' +
            '<p style="color: #fbbf24; font-size: 2rem; margin-top: 0.5rem;">+' + xp + ' XP</p>' +
            '<button class="btn btn-warning btn-lg mt-4" onclick="this.parentElement.remove()">続ける</button>';

        document.body.appendChild(overlay);

        // カード全体にエフェクト
        var bossCard = document.getElementById('boss-view');
        if (bossCard) {
            bossCard.classList.add('boss-defeated-effect');
        }

        // プロジェクト完全制覇の場合は追加エフェクト表示
        if (projectCleared) {
            setTimeout(function() {
                showProjectClearOverlay();
            }, 2000);
        }
    }

    // プロジェクト完全制覇エフェクト表示
    function showProjectClearOverlay() {
        // 既存のオーバーレイを削除
        var existingOverlay = document.querySelector('.victory-overlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        // 紙吹雪コンテナを作成
        var confettiContainer = document.createElement('div');
        confettiContainer.className = 'confetti-container';
        document.body.appendChild(confettiContainer);

        // 紙吹雪を生成
        var colors = ['#fbbf24', '#ef4444', '#10b981', '#6366f1', '#ec4899', '#f97316'];
        for (var i = 0; i < 100; i++) {
            createConfetti(confettiContainer, colors);
        }

        // プロジェクト完全制覇オーバーレイ
        var overlay = document.createElement('div');
        overlay.className = 'project-clear-overlay';
        overlay.innerHTML =
            '<h1>PROJECT CLEAR!</h1>' +
            '<p style="color: #f3f4f6; font-size: 1.5rem; margin-top: 1rem;">プロジェクト完全制覇！</p>' +
            '<p style="color: #10b981; font-size: 1.2rem; margin-top: 0.5rem;">全てのボスを討伐しました！</p>' +
            '<a href="<?php echo \Uri::create('dashboard'); ?>" class="btn btn-success btn-lg mt-4">ダッシュボードに戻る</a>';

        document.body.appendChild(overlay);

        // 紙吹雪を10秒後に削除
        setTimeout(function() {
            confettiContainer.remove();
        }, 10000);
    }

    // 紙吹雪を1つ生成
    function createConfetti(container, colors) {
        var confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
        confetti.style.animationDelay = Math.random() * 5 + 's';
        container.appendChild(confetti);
    }

    // レベルアップメッセージ表示
    function showLevelUpMessage(newLevel) {
        var alert = document.getElementById('level-up-alert');
        alert.innerHTML = '<strong>LEVEL UP!</strong> レベルが <span class="fs-4">' + newLevel + '</span> になりました！';
        alert.classList.remove('d-none', 'alert-warning');
        alert.classList.add('alert-success');

        setTimeout(function() {
            alert.classList.add('d-none');
        }, 5000);

        // ナビバーのレベル表示を更新
        updateUserStatus();
    }

    // エラーメッセージ表示
    function showErrorMessage(message) {
        var alert = document.getElementById('level-up-alert');
        alert.innerHTML = '<strong>Error:</strong> ' + message;
        alert.classList.remove('d-none', 'alert-success', 'alert-warning');
        alert.classList.add('alert-danger');

        setTimeout(function() {
            alert.classList.add('d-none');
        }, 3000);
    }

    // 成功メッセージ表示
    function showSuccessMessage(message) {
        var alert = document.getElementById('level-up-alert');
        alert.innerHTML = '<strong>Success:</strong> ' + message;
        alert.classList.remove('d-none', 'alert-danger', 'alert-warning');
        alert.classList.add('alert-success');

        setTimeout(function() {
            alert.classList.add('d-none');
        }, 3000);
    }

    // ナビバーのユーザーステータスを更新
    function updateUserStatus() {
        $.ajax({
            url: '<?php echo \Uri::base(); ?>api/battle/user_status',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // レベル表示を更新
                    var levelEl = document.querySelector('.user-level');
                    if (levelEl) {
                        levelEl.textContent = 'Lv.' + response.level;
                    }

                    // XPバーを更新
                    var xpBar = document.querySelector('.xp-bar');
                    if (xpBar) {
                        xpBar.style.width = response.xp_progress + '%';
                    }

                    // XPテキストを更新
                    var xpText = document.querySelector('.xp-text');
                    if (xpText) {
                        xpText.textContent = 'XP: ' + response.xp + ' / ' + response.next_level_xp;
                    }
                }
            }
        });
    }
});
</script>
