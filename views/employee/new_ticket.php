<?php
/**
 * Employee — new ticket form.
 *
 * Vars: $categories, $priorities (lookup rows), $errors (field => lang key),
 * $old (sticky input), $pageTitle. All labels via t(); category option text
 * uses the localized DB name for the current language, priority via lang keys.
 *
 * @var array $categories
 * @var array $priorities
 * @var array $errors
 * @var array $old
 */
$lang = Helpers::lang();

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('ticket_new_title')); ?></h1>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?php echo e(t($errors['form'])); ?></div>
                <?php endif; ?>

                <div class="card">
                    <form method="post" action="<?php echo e(BASE_URL); ?>?page=ticket_new" enctype="multipart/form-data">
                        <?php echo Auth::csrfField(); ?>

                        <div class="form-group">
                            <label class="form-label" for="title"><?php echo e(t('label_title')); ?></label>
                            <input class="form-control" type="text" id="title" name="title"
                                   maxlength="200" value="<?php echo e($old['title']); ?>" required>
                            <?php if (!empty($errors['title'])): ?>
                                <p class="form-error"><?php echo e(t($errors['title'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="category_id"><?php echo e(t('label_category')); ?></label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value=""><?php echo e(t('select_placeholder')); ?></option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>"
                                        <?php echo (string) $c['id'] === (string) $old['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo e($lang === 'ar' ? $c['name_ar'] : $c['name_en']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['category_id'])): ?>
                                <p class="form-error"><?php echo e(t($errors['category_id'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="priority_id"><?php echo e(t('label_priority')); ?></label>
                            <select class="form-control" id="priority_id" name="priority_id" required>
                                <option value=""><?php echo e(t('select_placeholder')); ?></option>
                                <?php foreach ($priorities as $p): ?>
                                    <?php $pKey = (int) $p['level'] === 1 ? 'priority_urgent'
                                        : ((int) $p['level'] === 2 ? 'priority_medium' : 'priority_low'); ?>
                                    <option value="<?php echo (int) $p['id']; ?>"
                                        <?php echo (string) $p['id'] === (string) $old['priority_id'] ? 'selected' : ''; ?>>
                                        <?php echo e(t($pKey)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['priority_id'])): ?>
                                <p class="form-error"><?php echo e(t($errors['priority_id'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description"><?php echo e(t('label_description')); ?></label>
                            <textarea class="form-control" id="description" name="description" required><?php echo e($old['description']); ?></textarea>
                            <?php if (!empty($errors['description'])): ?>
                                <p class="form-error"><?php echo e(t($errors['description'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Reusable upload field; surface any attachment error inline.
                        $fileError = $errors['attachment'] ?? null;
                        require VIEWS_PATH . '/components/file_upload.php';
                        ?>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit"><?php echo e(t('ticket_submit')); ?></button>
                            <a class="btn btn-outline" href="<?php echo e(BASE_URL); ?>?page=ticket_my"><?php echo e(t('action_cancel')); ?></a>
                        </div>
                    </form>
                </div>
<?php
require VIEWS_PATH . '/layout/footer.php';
