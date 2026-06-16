<?php
/**
 * Admin — user management: the user list (with role/active filters and
 * pagination) plus an add/edit form on the same page.
 *
 * Vars:
 *   $users        joined user rows (User::all)
 *   $departments  department lookup rows for the form selector
 *   $roles        assignable role codes
 *   $filters      active list filters
 *   $old          sticky form input (also pre-filled when editing)
 *   $errors       field => lang key
 *   $editing      true when the form edits an existing user
 *   $saved        'created' | 'updated' | 'toggled' | null (PRG banner)
 *   $pageNum / $pages / $total  pagination
 *
 * All visible text via t(); employee_id is read-only when editing (LDAP key).
 *
 * @var array       $users
 * @var array       $departments
 * @var array       $roles
 * @var array       $filters
 * @var array       $old
 * @var array       $errors
 * @var bool        $editing
 * @var string|null $saved
 * @var int         $pageNum
 * @var int         $pages
 */
$lang = Helpers::lang();

// Active filters as a query fragment, reused by the pagination links.
$filterQuery = http_build_query(array_filter([
    'role'   => $filters['role']      ?? null,
    'active' => isset($filters['is_active']) ? (string) $filters['is_active'] : null,
], static function ($v) { return $v !== null && $v !== ''; }));
$filterSuffix = $filterQuery !== '' ? '&amp;' . str_replace('&', '&amp;', $filterQuery) : '';

// Open the add/edit modal on load when editing a user or when a submit failed
// validation (the page re-renders in place, no redirect). Presentation only.
$userModalOpen = $editing || !empty($errors);

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('admin_users_title')); ?></h1>

                <?php if ($saved !== null): ?>
                    <div class="alert alert--success">
                        <?php
                        $savedKey = $saved === 'created' ? 'admin_user_created'
                            : ($saved === 'updated' ? 'admin_user_updated' : 'admin_user_toggled');
                        echo e(t($savedKey));
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?php echo e(t($errors['form'])); ?></div>
                <?php endif; ?>

                <div class="toolbar toolbar--end">
                    <button type="button" class="btn btn-primary modal-trigger" data-modal-open="userModal">
                        <?php echo e(t('admin_user_add_title')); ?>
                    </button>
                </div>

                <div class="modal" id="userModal" role="dialog" aria-modal="true"
                     aria-labelledby="userModalTitle"<?php echo $userModalOpen ? ' data-open-on-load="1"' : ''; ?>>
                    <div class="modal__overlay" data-modal-close></div>
                    <div class="modal__dialog" role="document">
                        <div class="modal__header">
                            <h2 class="modal__title" id="userModalTitle">
                                <?php echo e($editing ? t('admin_user_edit_title') : t('admin_user_add_title')); ?>
                            </h2>
                            <button type="button" class="modal__close" data-modal-close
                                    aria-label="<?php echo e(t('action_close')); ?>">&times;</button>
                        </div>
                        <div class="modal__body">
                            <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_users">
                                <?php echo Auth::csrfField(); ?>
                                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                                <?php if ($editing): ?>
                                    <input type="hidden" name="id" value="<?php echo e($old['id']); ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="employee_id"><?php echo e(t('label_employee_id')); ?></label>
                                    <input class="form-control" type="text" id="employee_id" name="employee_id"
                                           maxlength="50" value="<?php echo e($old['employee_id']); ?>"
                                           <?php echo $editing ? 'readonly' : 'required'; ?>>
                                    <?php if (!empty($errors['employee_id'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['employee_id'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="full_name"><?php echo e(t('label_full_name')); ?></label>
                                    <input class="form-control" type="text" id="full_name" name="full_name"
                                           maxlength="100" value="<?php echo e($old['full_name']); ?>" required>
                                    <?php if (!empty($errors['full_name'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['full_name'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="email"><?php echo e(t('label_email')); ?></label>
                                    <input class="form-control" type="email" id="email" name="email"
                                           maxlength="100" value="<?php echo e($old['email']); ?>" required>
                                    <?php if (!empty($errors['email'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['email'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="role"><?php echo e(t('label_role')); ?></label>
                                    <select class="form-control" id="role" name="role" required>
                                        <option value=""><?php echo e(t('select_placeholder')); ?></option>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?php echo e($r); ?>"
                                                <?php echo $old['role'] === $r ? 'selected' : ''; ?>>
                                                <?php echo e(t('role_' . $r)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($errors['role'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['role'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="department_id"><?php echo e(t('label_department')); ?></label>
                                    <select class="form-control" id="department_id" name="department_id">
                                        <option value=""><?php echo e(t('department_none')); ?></option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?php echo (int) $d['id']; ?>"
                                                <?php echo $old['department_id'] === (string) $d['id'] ? 'selected' : ''; ?>>
                                                <?php echo e($lang === 'ar' ? $d['name_ar'] : $d['name_en']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($errors['department_id'])): ?>
                                        <p class="form-error"><?php echo e(t($errors['department_id'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="is_active"><?php echo e(t('label_account_status')); ?></label>
                                    <select class="form-control" id="is_active" name="is_active">
                                        <option value="1" <?php echo $old['is_active'] === '1' ? 'selected' : ''; ?>>
                                            <?php echo e(t('status_active')); ?>
                                        </option>
                                        <option value="0" <?php echo $old['is_active'] === '0' ? 'selected' : ''; ?>>
                                            <?php echo e(t('status_inactive')); ?>
                                        </option>
                                    </select>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" type="submit">
                                        <?php echo e($editing ? t('action_save') : t('admin_user_add')); ?>
                                    </button>
                                    <?php if ($editing): ?>
                                        <a class="btn btn-outline" href="<?php echo e(BASE_URL); ?>?page=admin_users">
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
                    <form method="get" action="<?php echo e(BASE_URL); ?>" class="filter-bar">
                        <input type="hidden" name="page" value="admin_users">

                        <select class="form-control" name="role" onchange="this.form.submit()">
                            <option value=""><?php echo e(t('filter_all_roles')); ?></option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo e($r); ?>"
                                    <?php echo ($filters['role'] ?? '') === $r ? 'selected' : ''; ?>>
                                    <?php echo e(t('role_' . $r)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="form-control" name="active" onchange="this.form.submit()">
                            <option value=""><?php echo e(t('filter_all_states')); ?></option>
                            <option value="1" <?php echo (isset($filters['is_active']) && $filters['is_active'] === 1) ? 'selected' : ''; ?>>
                                <?php echo e(t('status_active')); ?>
                            </option>
                            <option value="0" <?php echo (isset($filters['is_active']) && $filters['is_active'] === 0) ? 'selected' : ''; ?>>
                                <?php echo e(t('status_inactive')); ?>
                            </option>
                        </select>

                        <noscript>
                            <button class="btn btn-primary btn-sm" type="submit"><?php echo e(t('action_filter')); ?></button>
                        </noscript>
                    </form>
                </div>

                <div class="card">
                    <?php if (!$users): ?>
                        <p class="empty-state"><?php echo e(t('no_results')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_employee_id')); ?></th>
                                        <th><?php echo e(t('label_full_name')); ?></th>
                                        <th><?php echo e(t('label_email')); ?></th>
                                        <th><?php echo e(t('label_role')); ?></th>
                                        <th><?php echo e(t('label_department')); ?></th>
                                        <th><?php echo e(t('label_account_status')); ?></th>
                                        <th><?php echo e(t('label_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <?php $isActive = (int) $u['is_active'] === 1; ?>
                                        <tr>
                                            <td><?php echo e($u['employee_id']); ?></td>
                                            <td><?php echo e($u['full_name']); ?></td>
                                            <td><?php echo e($u['email']); ?></td>
                                            <td><?php echo e(t('role_' . $u['role'])); ?></td>
                                            <td>
                                                <?php
                                                if ($u['department_id'] === null) {
                                                    echo e(t('department_none'));
                                                } else {
                                                    echo e($lang === 'ar' ? $u['department_name_ar'] : $u['department_name_en']);
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
                                                   href="<?php echo e(BASE_URL); ?>?page=admin_users&amp;edit=<?php echo (int) $u['id']; ?>">
                                                    <?php echo e(t('action_edit')); ?>
                                                </a>
                                                <form method="post" action="<?php echo e(BASE_URL); ?>?page=admin_users"
                                                      class="inline-form">
                                                    <?php echo Auth::csrfField(); ?>
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
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

                        <?php if ($pages > 1): ?>
                            <nav class="pagination" aria-label="<?php echo e(t('pagination_label')); ?>">
                                <?php if ($pageNum > 1): ?>
                                    <a class="btn btn-outline btn-sm"
                                       href="<?php echo e(BASE_URL); ?>?page=admin_users&amp;p=<?php echo $pageNum - 1; ?><?php echo $filterSuffix; ?>">
                                        <?php echo e(t('pagination_prev')); ?>
                                    </a>
                                <?php endif; ?>
                                <span class="pagination__info">
                                    <?php echo e(t('pagination_info', ['current' => $pageNum, 'total' => $pages])); ?>
                                </span>
                                <?php if ($pageNum < $pages): ?>
                                    <a class="btn btn-outline btn-sm"
                                       href="<?php echo e(BASE_URL); ?>?page=admin_users&amp;p=<?php echo $pageNum + 1; ?><?php echo $filterSuffix; ?>">
                                        <?php echo e(t('pagination_next')); ?>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
<?php
require VIEWS_PATH . '/layout/footer.php';
