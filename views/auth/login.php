<?php
/**
 * Login view — standalone page (no sidebar), styled with the shared design
 * tokens from public/assets/css/style.css (phase 3).
 *
 * Default language is Arabic (RTL); a language switcher stores the choice in
 * the session via ?lang=. Every visible string comes from lang/ via t();
 * no hardcoded UI text. Expects: $errorKey (string|null), $pageTitle.
 *
 * @var string|null $errorKey
 * @var string      $pageTitle
 */
$lang = Helpers::lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo e($lang); ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?> — <?php echo e(t('app_name')); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="lang-<?php echo e($lang); ?>">
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-card__brand">
                <?php if (is_file(BASE_PATH . '/public/assets/img/logo.webp')): ?>
                    <img class="auth-card__logo" src="/assets/img/logo.webp"
                         alt="<?php echo e(t('bank_name')); ?>">
                <?php else: ?>
                    <span class="auth-card__brand-name"><?php echo e(t('bank_name')); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="auth-card__title"><?php echo e(t('login_heading')); ?></h1>

            <div class="lang-switch" aria-label="<?php echo e(t('lang_switch')); ?>">
                <a href="<?php echo e(BASE_URL); ?>?page=login&amp;lang=ar"
                   class="<?php echo $lang === 'ar' ? 'active' : ''; ?>"><?php echo e(t('lang_name_ar')); ?></a>
                <span class="sep">|</span>
                <a href="<?php echo e(BASE_URL); ?>?page=login&amp;lang=en"
                   class="<?php echo $lang === 'en' ? 'active' : ''; ?>"><?php echo e(t('lang_name_en')); ?></a>
            </div>

            <?php if ($errorKey !== null): ?>
                <div class="alert alert--danger"><?php echo e(t($errorKey)); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo e(BASE_URL); ?>?page=login">
                <?php echo Auth::csrfField(); ?>

                <div class="form-group">
                    <label class="form-label" for="employee_id"><?php echo e(t('login_employee_id')); ?></label>
                    <input class="form-control" type="text" id="employee_id" name="employee_id"
                           autocomplete="username" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password"><?php echo e(t('login_password')); ?></label>
                    <input class="form-control" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button class="btn btn-accent" type="submit"><?php echo e(t('login_submit')); ?></button>
            </form>
        </div>
    </div>
</body>
</html>
