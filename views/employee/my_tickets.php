<?php
/**
 * Employee — "my tickets" list (own tickets only).
 *
 * Vars: $tickets (joined rows), $statuses/$categories/$priorities (filter
 * options), $filters (active filters), $createdNumber (success banner after a
 * PRG create), pagination $pageNum / $pages / $total, $pageTitle.
 * Status & priority are rendered via the badge components.
 *
 * @var array       $tickets
 * @var array       $statuses
 * @var array       $categories
 * @var array       $priorities
 * @var array       $filters
 * @var string|null $createdNumber
 * @var int         $pageNum
 * @var int         $pages
 */
$lang = Helpers::lang();

// The new-ticket modal is a convenience shortcut; its form posts to ?page=
// ticket_new, which owns validation and (on error) re-renders its own page. So
// here the modal always starts blank — sticky input/errors live on that page.
$old    = isset($old) && is_array($old) ? $old : ['title' => '', 'description' => '', 'category_id' => '', 'priority_id' => ''];
$errors = isset($errors) && is_array($errors) ? $errors : [];

// Active filters as a query fragment, reused by the pagination links.
$filterQuery = http_build_query(array_filter([
    'status_id'   => $filters['status_id']   ?? null,
    'category_id' => $filters['category_id'] ?? null,
    'priority_id' => $filters['priority_id'] ?? null,
]));
$filterSuffix = $filterQuery !== '' ? '&amp;' . str_replace('&', '&amp;', $filterQuery) : '';

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('ticket_my_title')); ?></h1>

                <?php if ($createdNumber !== null && $createdNumber !== ''): ?>
                    <div class="alert alert--success">
                        <?php echo e(t('ticket_created', ['ticket_number' => $createdNumber])); ?>
                    </div>
                <?php endif; ?>

                <div class="toolbar toolbar--end">
                    <a class="btn btn-primary" href="<?php echo e(BASE_URL); ?>?page=ticket_new"
                       data-modal-open="ticketModal">
                        <?php echo e(t('nav_new_ticket')); ?>
                    </a>
                </div>

                <div class="modal" id="ticketModal" role="dialog" aria-modal="true"
                     aria-labelledby="ticketModalTitle">
                    <div class="modal__overlay" data-modal-close></div>
                    <div class="modal__dialog" role="document">
                        <div class="modal__header">
                            <h2 class="modal__title" id="ticketModalTitle"><?php echo e(t('ticket_new_title')); ?></h2>
                            <button type="button" class="modal__close" data-modal-close
                                    aria-label="<?php echo e(t('action_close')); ?>">&times;</button>
                        </div>
                        <div class="modal__body">
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
                                    <button class="btn btn-outline" type="button" data-modal-close>
                                        <?php echo e(t('action_cancel')); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <form method="get" action="<?php echo e(BASE_URL); ?>" class="filter-bar">
                        <input type="hidden" name="page" value="ticket_my">

                        <select class="form-control" name="status_id" onchange="this.form.submit()">
                            <option value=""><?php echo e(t('filter_all_statuses')); ?></option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo (int) $s['id']; ?>"
                                    <?php echo ($filters['status_id'] ?? null) === (int) $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo e(t('status_' . $s['code'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="form-control" name="category_id" onchange="this.form.submit()">
                            <option value=""><?php echo e(t('filter_all_categories')); ?></option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>"
                                    <?php echo ($filters['category_id'] ?? null) === (int) $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($lang === 'ar' ? $c['name_ar'] : $c['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="form-control" name="priority_id" onchange="this.form.submit()">
                            <option value=""><?php echo e(t('filter_all_priorities')); ?></option>
                            <?php foreach ($priorities as $p): ?>
                                <?php $pKey = (int) $p['level'] === 1 ? 'priority_urgent'
                                    : ((int) $p['level'] === 2 ? 'priority_medium' : 'priority_low'); ?>
                                <option value="<?php echo (int) $p['id']; ?>"
                                    <?php echo ($filters['priority_id'] ?? null) === (int) $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo e(t($pKey)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <noscript>
                            <button class="btn btn-primary btn-sm" type="submit"><?php echo e(t('action_filter')); ?></button>
                        </noscript>
                    </form>
                </div>

                <div class="card">
                    <?php if (!$tickets): ?>
                        <p class="empty-state"><?php echo e(t('no_results')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table" data-enhance>
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_ticket_number')); ?></th>
                                        <th><?php echo e(t('label_title')); ?></th>
                                        <th><?php echo e(t('label_category')); ?></th>
                                        <th><?php echo e(t('label_priority')); ?></th>
                                        <th><?php echo e(t('label_status')); ?></th>
                                        <th><?php echo e(t('label_created_at')); ?></th>
                                        <th data-no-sort><?php echo e(t('label_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $row): ?>
                                        <tr>
                                            <td><?php echo e($row['ticket_number']); ?></td>
                                            <td>
                                                <?php echo e($row['title']); ?>
                                                <?php if (!empty($row['has_attachment'])): ?>
                                                    <span class="attach-indicator" title="<?php echo e(t('label_attachment')); ?>"
                                                          aria-label="<?php echo e(t('label_attachment')); ?>">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                             stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                             stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e($lang === 'ar' ? $row['category_name_ar'] : $row['category_name_en']); ?></td>
                                            <td><?php $priorityLevel = (int) $row['priority_level']; require VIEWS_PATH . '/components/badge_priority.php'; ?></td>
                                            <td><?php $statusCode = (string) $row['status_code']; require VIEWS_PATH . '/components/badge_status.php'; ?></td>
                                            <td><?php echo e(Helpers::formatDate($row['created_at'])); ?></td>
                                            <td>
                                                <a class="btn btn-outline btn-sm"
                                                   href="<?php echo e(BASE_URL); ?>?page=ticket_view&amp;id=<?php echo (int) $row['id']; ?>">
                                                    <?php echo e(t('action_view')); ?>
                                                </a>
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
                                       href="<?php echo e(BASE_URL); ?>?page=ticket_my&amp;p=<?php echo $pageNum - 1; ?><?php echo $filterSuffix; ?>">
                                        <?php echo e(t('pagination_prev')); ?>
                                    </a>
                                <?php endif; ?>
                                <span class="pagination__info">
                                    <?php echo e(t('pagination_info', ['current' => $pageNum, 'total' => $pages])); ?>
                                </span>
                                <?php if ($pageNum < $pages): ?>
                                    <a class="btn btn-outline btn-sm"
                                       href="<?php echo e(BASE_URL); ?>?page=ticket_my&amp;p=<?php echo $pageNum + 1; ?><?php echo $filterSuffix; ?>">
                                        <?php echo e(t('pagination_next')); ?>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
<?php
require VIEWS_PATH . '/layout/footer.php';
