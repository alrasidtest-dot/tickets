<?php
/**
 * Dashboard view — role-aware landing page (rebrand phase 1/2).
 *
 * Renders four summary stat cards for the signed-in role, a welcome banner and
 * a "recent tickets" table, all using the shared bank-identity design tokens.
 * Every visible string comes from lang/ via t(); no hardcoded UI text.
 *
 * @var string                         $role     employee | agent | admin
 * @var string                         $fullName
 * @var array<string,int|float>        $stats    from Ticket::dashboardStats()
 * @var array<int,array<string,mixed>> $recent   latest 5 tickets in scope
 * @var string                         $pageTitle
 */

// Map the role to its translation key.
$roleKey = 'role_' . $role;

// Detail-view route key differs for agents (their own controller).
$viewPage = $role === 'agent' ? 'agent_ticket_view' : 'ticket_view';

// Inline SVG icons (20px, no external icon library — FRONTEND_GUIDE.md).
$dashIcon = [
    'total'       => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/><path d="M9 5v14"/></svg>',
    'open'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    'in_progress' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'resolved'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>',
    'assigned'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 11 2 2 4-4"/></svg>',
    'unassigned'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    'overdue'     => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>',
    'avg'         => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/></svg>',
];

// Resolve status code -> id so single-status cards can deep-link to the list
// page with a pre-applied status_id filter ($statusList comes from the controller).
$statusId = [];
foreach ($statusList as $s) {
    $statusId[$s['code']] = (int) $s['id'];
}

// Each role's cards navigate to that role's ticket list. The chevron icon and
// hover state (see style.css) signal the cards are clickable.
$listPage = $role === 'employee' ? 'ticket_my'
    : ($role === 'agent' ? 'agent_dashboard' : 'admin_tickets');

// Build a list-page URL, optionally pre-filtered. Null/empty params are dropped,
// so cards whose metric has no clean single-status filter just open the full list.
$listLink = function (array $params = []) use ($listPage) {
    $query = http_build_query(array_merge(['page' => $listPage], array_filter(
        $params,
        static function ($v) { return $v !== null && $v !== ''; }
    )));
    return BASE_URL . '?' . $query;
};

// Per-role stat-card definitions: [stats key, label key, colour variant, icon, href].
if ($role === 'employee') {
    $cards = [
        ['total',       'dash_total',       'primary', $dashIcon['total'],       $listLink()],
        ['open',        'dash_open',        'accent',  $dashIcon['open'],        $listLink()],
        ['in_progress', 'dash_in_progress', 'warning', $dashIcon['in_progress'], $listLink(['status_id' => $statusId['in_progress'] ?? null])],
        ['resolved',    'dash_resolved',    'success', $dashIcon['resolved'],    $listLink(['status_id' => $statusId['resolved'] ?? null])],
    ];
} elseif ($role === 'agent') {
    $cards = [
        ['assigned_to_me', 'dash_assigned_to_me', 'primary', $dashIcon['assigned'],    $listLink()],
        ['unassigned',     'dash_unassigned',     'accent',  $dashIcon['unassigned'],  $listLink()],
        ['in_progress',    'dash_in_progress',    'warning', $dashIcon['in_progress'], $listLink(['status_id' => $statusId['in_progress'] ?? null])],
        ['overdue',        'dash_overdue',        'danger',  $dashIcon['overdue'],     $listLink()],
    ];
} else {
    $cards = [
        ['total',                'dash_total',          'primary', $dashIcon['total'],   $listLink()],
        ['open',                 'dash_open',           'accent',  $dashIcon['open'],    $listLink()],
        ['overdue',              'dash_overdue',        'danger',  $dashIcon['overdue'], $listLink()],
        // Average resolution is a report metric, not a list filter — link to reports.
        ['avg_resolution_hours', 'dash_avg_resolution', 'success', $dashIcon['avg'],     BASE_URL . '?page=admin_reports'],
    ];
}

// Chevron shown at the trailing edge of each card to indicate navigation.
$dashGoIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>';

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('dashboard_title')); ?></h1>

                <div class="dash-stats">
                    <?php foreach ($cards as [$statKey, $labelKey, $variant, $svg, $href]): ?>
                        <a class="dash-card dash-card--<?php echo e($variant); ?> dash-card--link"
                           href="<?php echo e($href); ?>">
                            <span class="dash-card__icon"><?php echo $svg; ?></span>
                            <span class="dash-card__body">
                                <span class="dash-card__value">
                                    <?php
                                    // The admin "average resolution" card shows a formatted
                                    // duration ("X ساعة") or a friendly fallback when zero;
                                    // every other card is a plain integer count.
                                    if ($statKey === 'avg_resolution_hours') {
                                        $avgHours = (int) ($stats[$statKey] ?? 0);
                                        echo $avgHours > 0
                                            ? e(t('dash_hours_value', ['hours' => $avgHours]))
                                            : e(t('dash_no_value'));
                                    } else {
                                        echo (int) ($stats[$statKey] ?? 0);
                                    }
                                    ?>
                                </span>
                                <span class="dash-card__label"><?php echo e(t($labelKey)); ?></span>
                            </span>
                            <span class="dash-card__go" aria-hidden="true"><?php echo $dashGoIcon; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="card dash-welcome">
                    <h2 class="card__title dash-welcome__title"><?php echo e(t('welcome_user', ['name' => $fullName])); ?></h2>
                    <p class="dash-welcome__role"><?php echo e(t('dashboard_role', ['role' => t($roleKey)])); ?></p>
                </div>

                <div class="card">
                    <h2 class="card__title dash-section-title"><?php echo e(t('dash_recent_title')); ?></h2>

                    <?php if (empty($recent)): ?>
                        <p class="empty-state"><?php echo e(t('dash_no_recent')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_ticket_number')); ?></th>
                                        <th><?php echo e(t('label_title')); ?></th>
                                        <th><?php echo e(t('label_status')); ?></th>
                                        <th><?php echo e(t('label_created_at')); ?></th>
                                        <th><?php echo e(t('label_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $row): ?>
                                        <tr>
                                            <td><?php echo e($row['ticket_number']); ?></td>
                                            <td><?php echo e($row['title']); ?></td>
                                            <td>
                                                <?php
                                                $statusCode = $row['status_code'];
                                                require VIEWS_PATH . '/components/badge_status.php';
                                                ?>
                                            </td>
                                            <td><?php echo e(formatDate($row['created_at'])); ?></td>
                                            <td>
                                                <a class="btn btn-outline btn-sm"
                                                   href="<?php echo e(BASE_URL); ?>?page=<?php echo e($viewPage); ?>&amp;id=<?php echo (int) $row['id']; ?>">
                                                    <?php echo e(t('action_view')); ?>
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
