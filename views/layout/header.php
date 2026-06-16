<?php
/**
 * Shared layout — document head + top bar.
 *
 * Included first by every authenticated page, followed by layout/sidebar.php
 * and closed by layout/footer.php. Opens the document, the .layout wrapper and
 * the top bar, then leaves .layout-body open for the sidebar + content.
 *
 * Reads identity straight from Auth so any page works without extra plumbing.
 * Optional: $pageTitle (string) for the <title>; defaults to the app name.
 *
 * All visible strings come from lang/ via t(); no hardcoded UI text.
 */
$lang = Helpers::lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';

$role     = Auth::role() ?? 'employee';
$fullName = Auth::fullName() ?? '';
$title    = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : t('app_name');

// Current routing key, used to highlight the active sidebar link.
$currentPage = isset($_GET['page']) && $_GET['page'] !== '' ? (string) $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="<?php echo e($lang); ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?> — <?php echo e(t('app_name')); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- Progressive enhancement flag: marks JS as available before paint so
         modal forms can be hidden behind their triggers without a flash. -->
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="lang-<?php echo e($lang); ?>">
<div class="layout">

    <header class="topbar">
        <button type="button" class="topbar__menu-btn" id="sidebarToggle"
                aria-label="<?php echo e(t('nav_toggle')); ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <span class="topbar__brand">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 17h6M9 13h6M7 21h10a2 2 0 0 0 2-2V7l-5-5H7a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2z"/>
            </svg>
            <?php echo e(t('app_name')); ?>
        </span>

        <span class="topbar__spacer"></span>

        <div class="topbar__actions">
            <span class="lang-switch" aria-label="<?php echo e(t('lang_switch')); ?>">
                <a href="<?php echo e(BASE_URL); ?>?page=<?php echo e($currentPage); ?>&amp;lang=ar"
                   class="<?php echo $lang === 'ar' ? 'active' : ''; ?>"><?php echo e(t('lang_name_ar')); ?></a>
                <span class="sep">|</span>
                <a href="<?php echo e(BASE_URL); ?>?page=<?php echo e($currentPage); ?>&amp;lang=en"
                   class="<?php echo $lang === 'en' ? 'active' : ''; ?>"><?php echo e(t('lang_name_en')); ?></a>
            </span>

            <?php require VIEWS_PATH . '/components/notifications_dropdown.php'; ?>

            <span class="topbar__avatar" aria-hidden="true"><?php
                echo e(mb_substr(trim($fullName), 0, 1, 'UTF-8'));
            ?></span>
            <span class="topbar__user">
                <span class="topbar__user-name"><?php echo e($fullName); ?></span>
                <span class="topbar__user-role"><?php echo e(t('role_' . $role)); ?></span>
            </span>

            <a class="btn btn-outline" href="<?php echo e(BASE_URL); ?>?page=logout"><?php echo e(t('logout')); ?></a>
        </div>
    </header>

    <div class="layout-body">
