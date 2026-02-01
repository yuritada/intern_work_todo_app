<!-- ボス編集画面 -->

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card card-quest">
            <div class="card-header">
                <h4 class="mb-0">ボスを編集</h4>
            </div>
            <div class="card-body">
                <?php if ( ! empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                        <li><?php echo \Security::htmlentities($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="<?php echo \Uri::create('mission/edit/' . $boss['id']); ?>" method="POST">
                    <?php echo \Form::csrf(); ?>

                    <div class="mb-3">
                        <label for="title" class="form-label text-light">ボス名（ミッション名）</label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               id="title"
                               name="title"
                               value="<?php echo \Security::htmlentities($input['title']); ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="boss_rank" class="form-label text-light">ボスランク（難易度）</label>
                        <select class="form-select bg-dark text-light border-secondary"
                                id="boss_rank"
                                name="boss_rank">
                            <?php foreach ($boss_ranks as $rank => $info): ?>
                            <option value="<?php echo $rank; ?>"
                                    <?php echo $input['boss_rank'] == $rank ? 'selected' : ''; ?>>
                                <?php echo \Security::htmlentities($info['label']); ?>
                                (HP倍率: x<?php echo $info['hp_multiplier']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="color: #9ca3af;">ランク変更時、HPは比率を維持して再計算されます</div>
                    </div>

                    <div class="mb-4">
                        <label for="deadline" class="form-label text-light">期限（任意）</label>
                        <input type="date"
                               class="form-control bg-dark text-light border-secondary"
                               id="deadline"
                               name="deadline"
                               value="<?php echo \Security::htmlentities($input['deadline']); ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-quest">
                            更新する
                        </button>
                        <a href="<?php echo \Uri::create('mission/detail/' . $boss['id']); ?>" class="btn btn-outline-light">
                            キャンセル
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
