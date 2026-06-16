<?php
/**
 * Admin — reports & statistics dashboard.
 *
 * Renders summary cards, two distribution charts (tickets by status and by
 * category) drawn with the locally bundled Chart.js (public/assets/js/
 * chart.min.js — no external CDN), a per-agent workload table, the average
 * resolution time and the list of overdue tickets.
 *
 * Chart data is handed to the browser as a single JSON blob (translated labels,
 * design-token colours); a textual legend under each canvas keeps the figures
 * readable even with JavaScript disabled. All visible strings come from lang/.
 *
 * Vars: $byStatus, $byCategory, $byAgent (aggregated rows), $totals
 * (total/open/closed), $avgResolution (avg_hours + closed_count), $overdue
 * (rows), $overdueCount, $pageTitle.
 *
 * @var array $byStatus
 * @var array $byCategory
 * @var array $byAgent
 * @var array $totals
 * @var array $avgResolution
 * @var array $overdue
 * @var int   $overdueCount
 */
$lang = Helpers::lang();

// ---- Status distribution: labels, values, colours (design tokens) ----------
$statusColorMap = [
    'new'         => '#3D2314', // --color-primary (dark brown)
    'in_progress' => '#E8821A', // --color-accent (golden orange)
    'on_hold'     => '#64748B', // --color-secondary
    'resolved'    => '#0E7A4E', // --color-success
    'closed'      => '#7A5C4A', // --color-text-muted (muted brown)
];
$statusLabels = [];
$statusValues = [];
$statusColors = [];
foreach ($byStatus as $r) {
    $statusLabels[] = t('status_' . $r['code']);
    $statusValues[] = (int) $r['cnt'];
    $statusColors[] = $statusColorMap[$r['code']] ?? '#64748B';
}

// ---- Category distribution: labels, values, cycling palette ----------------
$catPalette = ['#3D2314', '#E8821A', '#0E7A4E', '#C96E0E', '#7A5C4A', '#B45309', '#B91C1C', '#64748B'];
$catLabels  = [];
$catValues  = [];
$catColors  = [];
foreach ($byCategory as $i => $r) {
    $catLabels[] = $lang === 'ar' ? $r['name_ar'] : $r['name_en'];
    $catValues[] = (int) $r['cnt'];
    $catColors[] = $catPalette[$i % count($catPalette)];
}

// Single JSON payload for the chart script (labels already translated). The
// HEX_* flags neutralise </script>, quotes and ampersands inside any label.
$chartData = json_encode([
    'status'   => ['labels' => $statusLabels, 'values' => $statusValues, 'colors' => $statusColors],
    'category' => ['labels' => $catLabels,    'values' => $catValues,    'colors' => $catColors],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// ---- Average resolution time -----------------------------------------------
$avgHours   = $avgResolution['avg_hours'];
$avgDisplay = $avgHours === null
    ? t('report_no_data')
    : t('report_hours_value', ['hours' => number_format($avgHours, 1)]);

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <h1 class="page-title"><?php echo e(t('admin_reports_title')); ?></h1>

                <!-- Summary figures -->
                <div class="stat-grid">
                    <div class="stat-card">
                        <span class="stat-card__value"><?php echo (int) $totals['total']; ?></span>
                        <span class="stat-card__label"><?php echo e(t('report_total_tickets')); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-card__value"><?php echo (int) $totals['open']; ?></span>
                        <span class="stat-card__label"><?php echo e(t('report_open_tickets')); ?></span>
                    </div>
                    <div class="stat-card stat-card--danger">
                        <span class="stat-card__value"><?php echo (int) $overdueCount; ?></span>
                        <span class="stat-card__label"><?php echo e(t('report_overdue_tickets')); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-card__value"><?php echo (int) $totals['closed']; ?></span>
                        <span class="stat-card__label"><?php echo e(t('report_closed_tickets')); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-card__value"><?php echo e($avgDisplay); ?></span>
                        <span class="stat-card__label">
                            <?php echo e(t('report_avg_resolution')); ?>
                            <?php if ($avgHours !== null): ?>
                                <small class="stat-card__note">
                                    <?php echo e(t('report_based_on', ['count' => (int) $avgResolution['closed_count']])); ?>
                                </small>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Distribution charts -->
                <div class="chart-grid">
                    <div class="card chart-card">
                        <h2 class="card__title"><?php echo e(t('report_by_status')); ?></h2>
                        <div class="chart-box">
                            <canvas id="chartStatus" aria-label="<?php echo e(t('report_by_status')); ?>" role="img"></canvas>
                        </div>
                        <dl class="chart-legend">
                            <?php foreach ($byStatus as $r): ?>
                                <div class="chart-legend__item">
                                    <dt>
                                        <span class="chart-legend__swatch"
                                              style="background: <?php echo e($statusColorMap[$r['code']] ?? '#64748B'); ?>;"></span>
                                        <?php echo e(t('status_' . $r['code'])); ?>
                                    </dt>
                                    <dd><?php echo (int) $r['cnt']; ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>

                    <div class="card chart-card">
                        <h2 class="card__title"><?php echo e(t('report_by_category')); ?></h2>
                        <div class="chart-box">
                            <canvas id="chartCategory" aria-label="<?php echo e(t('report_by_category')); ?>" role="img"></canvas>
                        </div>
                        <dl class="chart-legend">
                            <?php foreach ($byCategory as $i => $r): ?>
                                <div class="chart-legend__item">
                                    <dt>
                                        <span class="chart-legend__swatch"
                                              style="background: <?php echo e($catPalette[$i % count($catPalette)]); ?>;"></span>
                                        <?php echo e($lang === 'ar' ? $r['name_ar'] : $r['name_en']); ?>
                                    </dt>
                                    <dd><?php echo (int) $r['cnt']; ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>

                <!-- Tickets per agent -->
                <div class="card">
                    <h2 class="card__title"><?php echo e(t('report_by_agent')); ?></h2>
                    <?php if (!$byAgent): ?>
                        <p class="empty-state"><?php echo e(t('no_results')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_assigned_to')); ?></th>
                                        <th><?php echo e(t('report_ticket_count')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($byAgent as $r): ?>
                                        <tr>
                                            <td>
                                                <?php if ($r['assignee_name'] === null): ?>
                                                    <span class="badge badge--warning"><?php echo e(t('unassigned')); ?></span>
                                                <?php else: ?>
                                                    <?php echo e($r['assignee_name']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo (int) $r['cnt']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Overdue tickets -->
                <div class="card">
                    <h2 class="card__title"><?php echo e(t('report_overdue_title')); ?></h2>
                    <?php if (!$overdue): ?>
                        <p class="empty-state"><?php echo e(t('report_no_overdue')); ?></p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php echo e(t('label_ticket_number')); ?></th>
                                        <th><?php echo e(t('label_title')); ?></th>
                                        <th><?php echo e(t('label_category')); ?></th>
                                        <th><?php echo e(t('label_priority')); ?></th>
                                        <th><?php echo e(t('label_status')); ?></th>
                                        <th><?php echo e(t('label_assigned_to')); ?></th>
                                        <th><?php echo e(t('report_sla_hours')); ?></th>
                                        <th><?php echo e(t('report_age_hours')); ?></th>
                                        <th><?php echo e(t('report_overdue_by')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdue as $row): ?>
                                        <?php $overdueBy = (int) $row['age_hours'] - (int) $row['sla_hours']; ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo e(BASE_URL); ?>?page=ticket_view&amp;id=<?php echo (int) $row['id']; ?>">
                                                    <?php echo e($row['ticket_number']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo e($row['title']); ?></td>
                                            <td><?php echo e($lang === 'ar' ? $row['category_name_ar'] : $row['category_name_en']); ?></td>
                                            <td><?php $priorityLevel = (int) $row['priority_level']; require VIEWS_PATH . '/components/badge_priority.php'; ?></td>
                                            <td><?php $statusCode = (string) $row['status_code']; require VIEWS_PATH . '/components/badge_status.php'; ?></td>
                                            <td>
                                                <?php if ($row['assignee_name'] === null): ?>
                                                    <span class="badge badge--warning"><?php echo e(t('unassigned')); ?></span>
                                                <?php else: ?>
                                                    <?php echo e($row['assignee_name']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo (int) $row['sla_hours']; ?></td>
                                            <td><?php echo (int) $row['age_hours']; ?></td>
                                            <td><span class="overdue-by">+<?php echo $overdueBy; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chart data + local Chart.js (no external CDN) -->
                <script>window.__reportData = <?php echo $chartData; ?>;</script>
                <script src="/assets/js/chart.min.js"></script>
                <script>
                    // Render the two distribution charts from window.__reportData.
                    // Labels are pre-translated server-side; colours are design
                    // tokens. Guard against the library failing to load.
                    (function () {
                        if (!window.Chart || !window.__reportData) { return; }
                        var data = window.__reportData;
                        var rtl  = document.documentElement.dir === 'rtl';
                        Chart.defaults.font.family =
                            getComputedStyle(document.body).fontFamily || 'sans-serif';

                        var statusEl = document.getElementById('chartStatus');
                        if (statusEl) {
                            new Chart(statusEl, {
                                type: 'doughnut',
                                data: {
                                    labels: data.status.labels,
                                    datasets: [{
                                        data: data.status.values,
                                        backgroundColor: data.status.colors,
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom', rtl: rtl }
                                    }
                                }
                            });
                        }

                        var catEl = document.getElementById('chartCategory');
                        if (catEl) {
                            new Chart(catEl, {
                                type: 'bar',
                                data: {
                                    labels: data.category.labels,
                                    datasets: [{
                                        data: data.category.values,
                                        backgroundColor: data.category.colors,
                                        borderWidth: 0
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: { beginAtZero: true, ticks: { precision: 0 } }
                                    }
                                }
                            });
                        }
                    })();
                </script>
<?php
require VIEWS_PATH . '/layout/footer.php';
