<?php
/**
 * Auth — session lifecycle, login state, RBAC guards and CSRF tokens.
 *
 * Session is started once here (Auth::start) and that call is made globally
 * from public/index.php for every page. Per-method role checks are the
 * responsibility of each controller via Auth::require / Auth::requireAny.
 *
 * Identity stored in the session: user_id, role, full_name, department_id, lang
 * (per docs/SECURITY_AUTH.md). department_id scopes the department-manager role
 * without a per-request DB lookup. The session also holds operational keys:
 * _last_activity (idle-timeout housekeeping) and _csrf (CSRF token).
 */
class Auth
{
    /** Idle timeout in seconds (30 minutes). */
    const IDLE_TIMEOUT = 1800;

    /**
     * Start the session (once) and enforce the idle timeout. Safe to call
     * on every request.
     *
     * @return void
     */
    public static function start()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        self::enforceIdleTimeout();
    }

    /**
     * Auto-logout after IDLE_TIMEOUT of inactivity, then bounce to the login
     * page with a "session expired" flag. Refreshes the activity stamp for
     * active sessions.
     *
     * @return void
     */
    private static function enforceIdleTimeout()
    {
        if (!self::check()) {
            return;
        }

        $last = $_SESSION['_last_activity'] ?? null;
        if ($last !== null && (time() - (int) $last) > self::IDLE_TIMEOUT) {
            self::logout();
            self::redirect('?page=login&expired=1');
        }

        $_SESSION['_last_activity'] = time();
    }

    /**
     * Whether a user is currently authenticated.
     *
     * @return bool
     */
    public static function check()
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Establish an authenticated session for the given user row. Regenerates
     * the session id to prevent fixation.
     *
     * @param array{id:int,role:string,full_name:string,department_id:?int} $user
     * @return void
     */
    public static function login(array $user)
    {
        session_regenerate_id(true);

        $_SESSION['user_id']        = (int) $user['id'];
        $_SESSION['role']           = (string) $user['role'];
        $_SESSION['full_name']      = (string) $user['full_name'];
        $_SESSION['department_id']  = isset($user['department_id']) && $user['department_id'] !== null
            ? (int) $user['department_id']
            : null;
        $_SESSION['lang']           = self::currentLang();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Destroy the authenticated session while preserving the chosen language
     * so the login page keeps the user's last language selection.
     *
     * @return void
     */
    public static function logout()
    {
        $lang = self::currentLang();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }
        session_destroy();

        // Fresh session that only remembers the language preference.
        session_start();
        session_regenerate_id(true);
        $_SESSION['lang'] = $lang;
    }

    /**
     * Current authenticated user id, or null.
     *
     * @return int|null
     */
    public static function id()
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Current authenticated user's role, or null.
     *
     * @return string|null
     */
    public static function role()
    {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Current authenticated user's full name, or null.
     *
     * @return string|null
     */
    public static function fullName()
    {
        return $_SESSION['full_name'] ?? null;
    }

    /**
     * Current authenticated user's department id, or null when the user has no
     * department (used to scope the department-manager role).
     *
     * @return int|null
     */
    public static function departmentId()
    {
        return isset($_SESSION['department_id']) && $_SESSION['department_id'] !== null
            ? (int) $_SESSION['department_id']
            : null;
    }

    // ---- RBAC guards -------------------------------------------------------

    /**
     * Require the current user to hold exactly the given role. Sends the user
     * to login when unauthenticated, or a 403 when authenticated but wrong
     * role.
     *
     * @param string $role
     * @return void
     */
    public static function require($role)
    {
        self::requireAny([$role]);
    }

    /**
     * Require the current user to hold one of the given roles.
     *
     * @param string[] $roles
     * @return void
     */
    public static function requireAny(array $roles)
    {
        if (!self::check()) {
            self::redirect('?page=login');
        }

        if (!in_array(self::role(), $roles, true)) {
            self::forbidden();
        }
    }

    /**
     * Emit a generic 403 page (details never leak to the user) and stop.
     *
     * @return void
     */
    private static function forbidden()
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        $dir  = self::currentLang() === 'ar' ? 'rtl' : 'ltr';
        $lang = self::currentLang();
        echo '<!DOCTYPE html><html lang="' . $lang . '" dir="' . $dir . '"><head>'
            . '<meta charset="utf-8"><title>403</title></head><body>'
            . '<p>' . Helpers::e(Helpers::t('access_denied')) . '</p>'
            . '</body></html>';
        exit;
    }

    // ---- CSRF --------------------------------------------------------------

    /**
     * Return the session CSRF token, creating one on first use.
     *
     * @return string
     */
    public static function csrfToken()
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /**
     * Hidden CSRF input for embedding inside a <form>.
     *
     * @return string
     */
    public static function csrfField()
    {
        return '<input type="hidden" name="csrf_token" value="'
            . Helpers::e(self::csrfToken()) . '">';
    }

    /**
     * Constant-time check of a submitted CSRF token against the session token.
     *
     * @param string|null $token
     * @return bool
     */
    public static function csrfVerify($token)
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Validated current language (session value or default).
     *
     * @return string
     */
    private static function currentLang()
    {
        $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
        return in_array($lang, Helpers::LANGS, true) ? $lang : DEFAULT_LANG;
    }

    /**
     * Redirect to a path relative to the front controller and stop.
     *
     * @param string $relative e.g. '?page=login'
     * @return void
     */
    private static function redirect($relative)
    {
        header('Location: ' . BASE_URL . $relative);
        exit;
    }
}
