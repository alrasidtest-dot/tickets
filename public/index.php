<?php
/**
 * index.php — single entry point and front controller.
 *
 * Responsibilities:
 *   - bootstrap constants, core classes and shared services,
 *   - start the session and enforce the idle timeout (Auth::start),
 *   - apply the language switch (?lang=) globally,
 *   - guard every page (except login) behind an authenticated session,
 *   - dispatch the ?page= key through Router to a controller method.
 *
 * Document root is public/ only; everything else lives outside and is
 * reached exclusively via includes from this file. Per-method role checks
 * (RBAC) live in the controllers, not here.
 */

declare(strict_types=1);

// ---- Bootstrap -------------------------------------------------------------
require __DIR__ . '/../config/constants.php';
require CORE_PATH . '/Database.php';
require CORE_PATH . '/Helpers.php';
require CORE_PATH . '/Auth.php';
require CORE_PATH . '/Router.php';

// Start session + enforce idle timeout for every request.
Auth::start();

// ---- Language switch (global) ---------------------------------------------
// A ?lang= on any page stores the choice in the session; not routing, just a
// cross-cutting preference handled before dispatch.
if (isset($_GET['lang']) && in_array($_GET['lang'], Helpers::LANGS, true)) {
    $_SESSION['lang'] = (string) $_GET['lang'];
}

// ---- Routing ---------------------------------------------------------------
// Query-string routing only: index.php?page=KEY (default: dashboard).
$page = isset($_GET['page']) && $_GET['page'] !== '' ? (string) $_GET['page'] : 'dashboard';

// Global session guard: every page except login requires authentication.
if ($page !== 'login' && !Auth::check()) {
    header('Location: ' . BASE_URL . '?page=login');
    exit;
}

$route = Router::resolve($page);

if ($route !== null) {
    [$controllerName, $method] = $route;
    require CONTROLLERS_PATH . '/' . $controllerName . '.php';
    $controller = new $controllerName();
    $controller->$method();
    exit;
}

// ---- Unknown route ---------------------------------------------------------
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
$lang = Helpers::lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo Helpers::e($lang); ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="utf-8">
    <title>404</title>
</head>
<body>
    <p>404 — <?php echo Helpers::e($page); ?></p>
</body>
</html>
