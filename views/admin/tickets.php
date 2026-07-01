<?php
/**
 * Admin — all-tickets overview: every ticket in the system with status,
 * category, priority and agent filters, pagination, and a link to each ticket's
 * detail page (where the admin can reassign / act).
 *
 * Vars: $tickets (joined rows), $statuses/$categories/$priorities/$agents
 * (filter options), $filters (active filters), pagination $pageNum/$pages/
 * $total, $pageTitle. Status & priority render via the badge components.
 *
 * @var array $tickets
 * @var array $statuses
 * @var array $categories
 * @var array $priorities
 * @var array $agents
 * @var array $filters
 * @var int   $pageNum
 * @var int   $pages
 */
$lang = Helpers::lang();

// Active filters as a query fragment, reused by the pagination links.
$activeAgent = !empty($filters['unassigned'])
    ? 'unassigned'
    : (!empty($filters['assigned_to']) ? (string) $filters['assigned_to'] : '');
$filterQuery = http_build_query(array_filter([
    'status_id'   => $filters['status_id']   ?? null,
    'category_id' => $filters['category_id'] ?? null,
    'priority_id' => $filters['priority_id'] ?? null,
    'agent'       => $activeAgent !== '' ? $activeAgent : null,
]));
$filterSuffix = $filterQuery !== '' ? '&amp;' . str_replace('&', '&amp;', $filterQuery) : '';

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('admin_tickets_title')); ?></h1>

                <div class="card">
                    <form method="get" action="<?php echo e(BASE_URL); ?>" class="filter-bar">
                        <input type="hidden" name="page" value="admin_tickets">

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

                        <select class="form-control" name="agent" onchange="this.form.submit()">
                            <option value=""><?php echo e(t('filter_all_agents')); ?></option>
                            <option value="unassigned"
                                <?php echo $activeAgent === 'unassigned' ? 'selected' : ''; ?>>
                                <?php echo e(t('unassigned')); ?>
                            </option>
                            <?php foreach ($agents as $a): ?>
                                <option value="<?php echo (int) $a['id']; ?>"
                                    <?php echo $activeAgent === (string) $a['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($a['full_name']); ?>
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
                                        <th><?php echo e(t('label_created_by')); ?></th>
                                        <th><?php echo e(t('label_category')); ?></th>
                                        <th><?php echo e(t('label_priority')); ?></th>
                                        <th><?php echo e(t('label_status')); ?></th>
                                        <th><?php echo e(t('label_assigned_to')); ?></th>
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
                                            <td><?php echo e($row['creator_name']); ?></td>
                                            <td><?php echo e($lang === 'ar' ? $row['category_name_ar'] : $row['category_name_en']); ?></td>
                                            <td><?php $priorityLevel = (int) $row['priority_level']; require VIEWS_PATH . '/components/badge_priority.php'; ?></td>
                                            <td><?php $statusCode = (string) $row['status_code']; require VIEWS_PATH . '/components/badge_status.php'; ?></td>
                                            <td>
                                                <?php if ($row['assigned_to'] === null): ?>
                                                    <span class="badge badge--warning"><?php echo e(t('unassigned')); ?></span>
                                                <?php else: ?>
                                                    <?php echo e($row['assignee_name']); ?>
                                                <?php endif; ?>
                                            </td>
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
                                       href="<?php echo e(BASE_URL); ?>?page=admin_tickets&amp;p=<?php echo $pageNum - 1; ?><?php echo $filterSuffix; ?>">
                                        <?php echo e(t('pagination_prev')); ?>
                                    </a>
                                <?php endif; ?>
                                <span class="pagination__info">
                                    <?php echo e(t('pagination_info', ['current' => $pageNum, 'total' => $pages])); ?>
                                </span>
                                <?php if ($pageNum < $pages): ?>
                                    <a class="btn btn-outline btn-sm"
                                       href="<?php echo e(BASE_URL); ?>?page=admin_tickets&amp;p=<?php echo $pageNum + 1; ?><?php echo $filterSuffix; ?>">
                                        <?php echo e(t('pagination_next')); ?>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
<?php
require VIEWS_PATH . '/layout/footer.php';
