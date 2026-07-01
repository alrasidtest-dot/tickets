<?php
/**
 * Admin — category & priority management. Two sections on one page: ticket
 * categories and ticket priorities, each supporting add / edit / enable-disable.
 *
 * Vars:
 *   $categories  Ticket::categoriesAll rows (id, name_ar, name_en, is_active)
 *   $priorities  Ticket::prioritiesAll rows (id, name_ar, name_en, level, sla_hours)
 *   $catOld      sticky category form input (pre-filled when editing)
 *   $priOld      sticky priority form input (pre-filled when editing)
 *   $editCat / $editPri  whether each form is editing an existing row
 *   $errors      field => lang key
 *   $saved       'category' | 'priority' | null (PRG banner)
 *
 * All visible text via t(); priority level labels reuse the priority_* keys.
 *
 * @var array       $categories
 * @var array       $priorities
 * @var array       $catOld
 * @var array       $priOld
 * @var bool        $editCat
 * @var bool        $editPri
 * @var array       $errors
 * @var string|null $saved
 */
$lang = Helpers::lang();

// Map a priority level (1/2/3) to its translation key.
$levelKey = static function ($level) {
    $level = (int) $level;
    return $level === 1 ? 'priority_urgent' : ($level === 2 ? 'priority_medium' : 'priority_low');
};

// Decide which modal (if any) should open on load: when editing a row, or when
// the matching form failed validation (no redirect happened, so the submitted
// action is still in $_POST). Presentation-only — no business logic here.
$submittedAction = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? (string) ($_POST['action'] ?? '') : '';
$hasErrors       = !empty($errors);
$catModalOpen    = $editCat || ($hasErrors && ($submittedAction === 'cat_create' || $submittedAction === 'cat_update'));
$priModalOpen    = $editPri || ($hasErrors && ($submittedAction === 'pri_create' || $submittedAction === 'pri_update'));

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('admin_categories_title')); ?></h1>

                <?php if ($saved !== null): ?>
                    <div class="alert alert--success">
                        <?php echo e($saved === 'category' ? t('admin_category_saved') : t('admin_priority_saved')); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?php echo e(t($errors['form'])); ?></div>
                <?php endif; ?>

                <!-- ===================== Categories ===================== -->
                <div class="toolbar">
                    <h2 class="toolbar__title"><?php echo e(t('admin_categories_list_title')); ?></h2>
                    <button type="button" class="btn btn-primary modal-trigger" data-modal-open="catModal">
                        <?php echo e(t('admin_category_add')); ?>
                    </button>
                </div>

                <div class="modal" id="catModal" role="dialog" aria-modal="true"
                     aria-labelledby="catModalTitle"<?php echo $catModalOpen ? ' data-open-on-load="1"' : ''; ?>>
                    <div class="modal__overlay" data-modal-close></div>
                    <div class="modal__dialog" role="document">
                        <div class="modal__header">
                            <h2 class="modal__title" id="catModalTitle">
                                <?php echo e($editCat ? t('admin_category_edit_title') : t('admin_category_add_title')); ?>
                            </h2>
                            <button type="button" class="modal__close" data-modal-close
                                    aria-label="<?php echo e(t('action_close')); ?>">&times;</button>
                        </div>
                        <div class="modal__body">
                            <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_categories">
                                <?php echo Auth::csrfField(); ?>
                                <input type="hidden" name="action" value="<?php echo $editCat ? 'cat_update' : 'cat_create'; ?>">
                                <?php if ($editCat): ?>
                                    <input type="hidden" name="id" value="<?php echo e($catOld['id']); ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="cat_name_ar"><?php echo e(t('label_name_ar')); ?></label>
                                    <input class="form-control" type="text" id="cat_name_ar" name="name_ar"
                                           maxlength="100" value="<?php echo e($catOld['name_ar']); ?>" required>
                                    <?php if (!$editPri && !empty($errors['name_ar'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['name_ar'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cat_name_en"><?php echo e(t('label_name_en')); ?></label>
                                    <input class="form-control" type="text" id="cat_name_en" name="name_en"
                                           maxlength="100" value="<?php echo e($catOld['name_en']); ?>" required>
                                    <?php if (!$editPri && !empty($errors['name_en'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['name_en'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cat_department_id"><?php echo e(t('label_department')); ?></label>
                                    <select class="form-control" id="cat_department_id" name="department_id">
                                        <option value=""><?php echo e(t('department_none')); ?></option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?php echo (int) $d['id']; ?>"
                                                <?php echo $catOld['department_id'] === (string) $d['id'] ? 'selected' : ''; ?>>
                                                <?php echo e($lang === 'ar' ? $d['name_ar'] : $d['name_en']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!$editPri && !empty($errors['department_id'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['department_id'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" type="submit">
                                        <?php echo e($editCat ? t('action_save') : t('admin_category_add')); ?>
                                    </button>
                                    <?php if ($editCat): ?>
                                        <a class="btn btn-outline" href="<?php echo e(BASE_URL); ?>?page=admin_categories">
                                            <?php echo e(t('action_cancel')); ?>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline" type="button" data-modal-close>
                                            <?php echo e(t('action_cancel')); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <?php if (!$categories): ?>
                        <p class="empty-state"><?php echo e(t('no_results')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table" data-enhance>
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_name_ar')); ?></th>
                                        <th><?php echo e(t('label_name_en')); ?></th>
                                        <th><?php echo e(t('label_department')); ?></th>
                                        <th><?php echo e(t('label_status')); ?></th>
                                        <th data-no-sort><?php echo e(t('label_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $c): ?>
                                        <?php $isActive = (int) $c['is_active'] === 1; ?>
                                        <tr>
                                            <td><?php echo e($c['name_ar']); ?></td>
                                            <td><?php echo e($c['name_en']); ?></td>
                                            <td>
                                                <?php
                                                if ($c['department_id'] === null) {
                                                    echo e(t('department_none'));
                                                } else {
                                                    echo e($lang === 'ar' ? $c['department_name_ar'] : $c['department_name_en']);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $isActive ? 'badge--success' : 'badge--muted'; ?>">
                                                    <?php echo e($isActive ? t('status_active') : t('status_inactive')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a class="btn btn-outline btn-sm"
                                                   href="<?php echo e(BASE_URL); ?>?page=admin_categories&amp;edit_cat=<?php echo (int) $c['id']; ?>">
                                                    <?php echo e(t('action_edit')); ?>
                                                </a>
                                                <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_categories"
                                                      class="inline-form">
                                                    <?php echo Auth::csrfField(); ?>
                                                    <input type="hidden" name="action" value="cat_toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                                    <input type="hidden" name="active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                                    <button class="btn btn-sm <?php echo $isActive ? 'btn-ghost-danger' : 'btn-primary'; ?>"
                                                            type="submit">
                                                        <?php echo e($isActive ? t('action_disable') : t('action_enable')); ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ===================== Priorities ===================== -->
                <div class="toolbar">
                    <h2 class="toolbar__title"><?php echo e(t('admin_priorities_list_title')); ?></h2>
                    <button type="button" class="btn btn-primary modal-trigger" data-modal-open="priModal">
                        <?php echo e(t('admin_priority_add')); ?>
                    </button>
                </div>

                <div class="modal" id="priModal" role="dialog" aria-modal="true"
                     aria-labelledby="priModalTitle"<?php echo $priModalOpen ? ' data-open-on-load="1"' : ''; ?>>
                    <div class="modal__overlay" data-modal-close></div>
                    <div class="modal__dialog" role="document">
                        <div class="modal__header">
                            <h2 class="modal__title" id="priModalTitle">
                                <?php echo e($editPri ? t('admin_priority_edit_title') : t('admin_priority_add_title')); ?>
                            </h2>
                            <button type="button" class="modal__close" data-modal-close
                                    aria-label="<?php echo e(t('action_close')); ?>">&times;</button>
                        </div>
                        <div class="modal__body">
                            <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_categories">
                                <?php echo Auth::csrfField(); ?>
                                <input type="hidden" name="action" value="<?php echo $editPri ? 'pri_update' : 'pri_create'; ?>">
                                <?php if ($editPri): ?>
                                    <input type="hidden" name="id" value="<?php echo e($priOld['id']); ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="pri_name_ar"><?php echo e(t('label_name_ar')); ?></label>
                                    <input class="form-control" type="text" id="pri_name_ar" name="name_ar"
                                           maxlength="50" value="<?php echo e($priOld['name_ar']); ?>" required>
                                    <?php if ($editPri && !empty($errors['name_ar'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['name_ar'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="pri_name_en"><?php echo e(t('label_name_en')); ?></label>
                                    <input class="form-control" type="text" id="pri_name_en" name="name_en"
                                           maxlength="50" value="<?php echo e($priOld['name_en']); ?>" required>
                                    <?php if ($editPri && !empty($errors['name_en'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['name_en'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="pri_level"><?php echo e(t('label_level')); ?></label>
                                    <select class="form-control" id="pri_level" name="level" required>
                                        <option value=""><?php echo e(t('select_placeholder')); ?></option>
                                        <?php foreach ([1, 2, 3] as $lvl): ?>
                                            <option value="<?php echo $lvl; ?>"
                                                <?php echo $priOld['level'] === (string) $lvl ? 'selected' : ''; ?>>
                                                <?php echo $lvl; ?> — <?php echo e(t($levelKey($lvl))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($errors['level'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['level'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="pri_sla"><?php echo e(t('label_sla_hours')); ?></label>
                                    <input class="form-control" type="number" id="pri_sla" name="sla_hours"
                                           min="1" value="<?php echo e($priOld['sla_hours']); ?>" required>
                                    <?php if (!empty($errors['sla_hours'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['sla_hours'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" type="submit">
                                        <?php echo e($editPri ? t('action_save') : t('admin_priority_add')); ?>
                                    </button>
                                    <?php if ($editPri): ?>
                                        <a class="btn btn-outline" href="<?php echo e(BASE_URL); ?>?page=admin_categories">
                                            <?php echo e(t('action_cancel')); ?>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline" type="button" data-modal-close>
                                            <?php echo e(t('action_cancel')); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <?php if (!$priorities): ?>
                        <p class="empty-state"><?php echo e(t('no_results')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table" data-enhance>
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_name_ar')); ?></th>
                                        <th><?php echo e(t('label_name_en')); ?></th>
                                        <th><?php echo e(t('label_level')); ?></th>
                                        <th><?php echo e(t('label_sla_hours')); ?></th>
                                        <th><?php echo e(t('label_status')); ?></th>
                                        <th data-no-sort><?php echo e(t('label_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($priorities as $p): ?>
                                        <?php $isActive = (int) $p['is_active'] === 1; ?>
                                        <tr>
                                            <td><?php echo e($p['name_ar']); ?></td>
                                            <td><?php echo e($p['name_en']); ?></td>
                                            <td><?php echo (int) $p['level']; ?> — <?php echo e(t($levelKey($p['level']))); ?></td>
                                            <td><?php echo (int) $p['sla_hours']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $isActive ? 'badge--success' : 'badge--muted'; ?>">
                                                    <?php echo e($isActive ? t('status_active') : t('status_inactive')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a class="btn btn-outline btn-sm"
                                                   href="<?php echo e(BASE_URL); ?>?page=admin_categories&amp;edit_pri=<?php echo (int) $p['id']; ?>">
                                                    <?php echo e(t('action_edit')); ?>
                                                </a>
                                                <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_categories"
                                                      class="inline-form">
                                                    <?php echo Auth::csrfField(); ?>
                                                    <input type="hidden" name="action" value="pri_toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                                    <input type="hidden" name="active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                                    <button class="btn btn-sm <?php echo $isActive ? 'btn-ghost-danger' : 'btn-primary'; ?>"
                                                            type="submit">
                                                        <?php echo e($isActive ? t('action_disable') : t('action_enable')); ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
<?php
require VIEWS_PATH . '/layout/footer.php';
