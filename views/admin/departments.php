<?php
/**
 * Admin — department management: the department list plus an add/edit form on
 * the same page. Departments back the manager routing model (categories and
 * users belong to a department).
 *
 * Vars:
 *   $departments  department rows (id, name_ar, name_en)
 *   $deptOld      sticky form input (pre-filled when editing)
 *   $editing      whether the form edits an existing department
 *   $errors       field => lang key
 *   $saved        'department' | null (PRG banner)
 *
 * All visible text via t().
 *
 * @var array       $departments
 * @var array       $deptOld
 * @var bool        $editing
 * @var array       $errors
 * @var string|null $saved
 */
$lang = Helpers::lang();

// Open the add/edit modal on load when editing or after a failed submit.
$deptModalOpen = $editing || !empty($errors);

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('admin_departments_title')); ?></h1>

                <?php if ($saved !== null): ?>
                    <div class="alert alert--success"><?php echo e(t('admin_department_saved')); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?php echo e(t($errors['form'])); ?></div>
                <?php endif; ?>

                <div class="toolbar toolbar--end">
                    <button type="button" class="btn btn-primary modal-trigger" data-modal-open="deptModal">
                        <?php echo e(t('admin_department_add')); ?>
                    </button>
                </div>

                <div class="modal" id="deptModal" role="dialog" aria-modal="true"
                     aria-labelledby="deptModalTitle"<?php echo $deptModalOpen ? ' data-open-on-load="1"' : ''; ?>>
                    <div class="modal__overlay" data-modal-close></div>
                    <div class="modal__dialog" role="document">
                        <div class="modal__header">
                            <h2 class="modal__title" id="deptModalTitle">
                                <?php echo e($editing ? t('admin_department_edit_title') : t('admin_department_add_title')); ?>
                            </h2>
                            <button type="button" class="modal__close" data-modal-close
                                    aria-label="<?php echo e(t('action_close')); ?>">&times;</button>
                        </div>
                        <div class="modal__body">
                            <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_departments">
                                <?php echo Auth::csrfField(); ?>
                                <input type="hidden" name="action" value="<?php echo $editing ? 'dept_update' : 'dept_create'; ?>">
                                <?php if ($editing): ?>
                                    <input type="hidden" name="id" value="<?php echo e($deptOld['id']); ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="dept_name_ar"><?php echo e(t('label_name_ar')); ?></label>
                                    <input class="form-control" type="text" id="dept_name_ar" name="name_ar"
                                           maxlength="100" value="<?php echo e($deptOld['name_ar']); ?>" required>
                                    <?php if (!empty($errors['name_ar'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['name_ar'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="dept_name_en"><?php echo e(t('label_name_en')); ?></label>
                                    <input class="form-control" type="text" id="dept_name_en" name="name_en"
                                           maxlength="100" value="<?php echo e($deptOld['name_en']); ?>" required>
                                    <?php if (!empty($errors['name_en'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['name_en'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" type="submit">
                                        <?php echo e($editing ? t('action_save') : t('admin_department_add')); ?>
                                    </button>
                                    <?php if ($editing): ?>
                                        <a class="btn btn-outline" href="<?php echo e(BASE_URL); ?>?page=admin_departments">
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
                    <?php if (!$departments): ?>
                        <p class="empty-state"><?php echo e(t('no_results')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table" data-enhance>
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_name_ar')); ?></th>
                                        <th><?php echo e(t('label_name_en')); ?></th>
                                        <th data-no-sort><?php echo e(t('label_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $d): ?>
                                        <tr>
                                            <td><?php echo e($d['name_ar']); ?></td>
                                            <td><?php echo e($d['name_en']); ?></td>
                                            <td>
                                                <a class="btn btn-outline btn-sm"
                                                   href="<?php echo e(BASE_URL); ?>?page=admin_departments&amp;edit=<?php echo (int) $d['id']; ?>">
                                                    <?php echo e(t('action_edit')); ?>
                                                </a>
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
