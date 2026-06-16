<?php
/**
 * Shared layout — fixed sidebar navigation.
 *
 * Included after layout/header.php. Renders a role-aware navigation list, then
 * opens .content-wrap / .content for the page body (closed in footer.php).
 *
 * Relies on $role and $currentPage set by header.php. Link targets follow the
 * {entity}_{action} routing convention and become live in later phases; the
 * active item is highlighted by comparing $currentPage.
 *
 * Each item: [page key, translation key, inline SVG icon]. All labels via t().
 */

// Inline SVG icons (no external icon library — FRONTEND_GUIDE.md).
$icon = [
    'dashboard'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
    'new'        => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
    'tickets'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/><path d="M9 5v14"/></svg>',
    'users'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'categories' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18M3 12h18M3 19h18"/></svg>',
    'reports'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="6"/><rect x="13" y="7" width="3" height="10"/></svg>',
];

// Role-specific navigation. The dashboard entry is shared by every role.
$nav = ['dashboard' => [['dashboard', 'nav_dashboard', $icon['dashboard']]]];
$nav['employee'] = [
    ['ticket_new', 'nav_new_ticket', $icon['new']],
    ['ticket_my',  'nav_my_tickets', $icon['tickets']],
];
$nav['agent'] = [
    ['agent_dashboard', 'nav_tickets', $icon['tickets']],
];
$nav['admin'] = [
    ['admin_tickets',    'nav_tickets',    $icon['tickets']],
    ['admin_users',      'nav_users',      $icon['users']],
    ['admin_categories', 'nav_categories', $icon['categories']],
    ['admin_reports',    'nav_reports',    $icon['reports']],
];

$items = array_merge($nav['dashboard'], $nav[$role] ?? []);
?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar__brand">
                <?php if (is_file(BASE_PATH . '/public/assets/img/logo.webp')): ?>
                    <span class="sidebar__logo-box">
                        <img class="sidebar__logo" src="/assets/img/logo.webp"
                             alt="<?php echo e(t('bank_name')); ?>">
                    </span>
                <?php else: ?>
                    <span class="sidebar__brand-name"><?php echo e(t('bank_name')); ?></span>
                <?php endif; ?>
            </div>

            <nav class="sidebar__nav" aria-label="<?php echo e(t('nav_main')); ?>">
                <?php foreach ($items as [$pageKey, $labelKey, $svg]): ?>
                    <a class="sidebar__link <?php echo $currentPage === $pageKey ? 'is-active' : ''; ?>"
                       href="<?php echo e(BASE_URL); ?>?page=<?php echo e($pageKey); ?>">
                        <?php echo $svg; ?>
                        <span><?php echo e(t($labelKey)); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="content-wrap">
            <main class="content">
