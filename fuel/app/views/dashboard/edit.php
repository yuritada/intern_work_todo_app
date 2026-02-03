<!-- プロジェクト編集画面 -->

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card card-quest">
            <div class="card-header">
                <h4 class="mb-0">冒険を編集</h4>
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

                <form action="<?php echo \Uri::create('dashboard/edit/' . $project['id']); ?>" method="POST">
                    <?php echo \Form::csrf(); ?>

                    <div class="mb-3">
                        <label for="title" class="form-label text-light">冒険名（プロジェクト名）</label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary"
                               id="title"
                               name="title"
                               value="<?php echo \Security::htmlentities($input['title']); ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="deadline" class="form-label text-light">期限（任意）</label>
                        <input type="date"
                               class="form-control bg-dark text-light border-secondary"
                               id="deadline"
                               name="deadline"
                               value="<?php echo \Security::htmlentities($input['deadline']); ?>">
                    </div>

                    <div class="mb-4">
                        <label for="memo" class="form-label text-light">メモ（任意）</label>
                        <textarea class="form-control bg-dark text-light border-secondary"
                                  id="memo"
                                  name="memo"
                                  rows="3"><?php echo \Security::htmlentities($input['memo']); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-quest">
                            更新する
                        </button>
                        <a href="<?php echo \Uri::create('dashboard/project/' . $project['id']); ?>" class="btn btn-outline-light">
                            キャンセル
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
