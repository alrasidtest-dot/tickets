<?php
/**
 * AuthController — login, logout and the post-login dashboard.
 *
 * LDAP authentication runs in the mode configured by config/ldap.php
 * ('mock' for development). After a successful bind the employee_id must
 * exist as an active row in the users table, otherwise access is denied
 * (no auto-registration — see docs/SECURITY_AUTH.md).
 */
require_once MODELS_PATH . '/User.php';

class AuthController
{
    /**
     * GET: render the login form. POST: validate CSRF + credentials and,
     * on success, open a session and redirect to the dashboard.
     *
     * @return void
     */
    public function login()
    {
        // Already signed in → straight to the dashboard.
        if (Auth::check()) {
            $this->redirect('?page=dashboard');
        }

        $errorKey = null;

        // Surface the idle-timeout message after an auto-logout.
        if (isset($_GET['expired'])) {
            $errorKey = 'session_expired';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errorKey = 'login_csrf_error';
            } else {
                $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
                $password   = (string) ($_POST['password'] ?? '');

                $user = $this->attemptLogin($employeeId, $password);
                if ($user !== null) {
                    Auth::login($user);
                    $this->redirect('?page=dashboard');
                }

                // Generic message only; technical detail stays in the log.
                $errorKey = 'login_error';
            }
        }

        // Variables consumed by the view.
        $pageTitle = Helpers::t('login_title');
        require VIEWS_PATH . '/auth/login.php';
    }

    /**
     * End the session and return to the login page.
     *
     * @return void
     */
    public function logout()
    {
        Auth::logout();
        $this->redirect('?page=login');
    }

    /**
     * Temporary post-login landing page. Accessible to every authenticated
     * role; replaced by role-specific dashboards in later phases.
     *
     * @return void
     */
    public function dashboard()
    {
        Auth::requireAny(['employee', 'agent', 'admin']);

        require_once MODELS_PATH . '/Ticket.php';

        $userId   = Auth::id();
        $role     = Auth::role();
        $fullName = Auth::fullName();

        // Role-aware summary figures for the dashboard stat cards.
        $stats = Ticket::dashboardStats($userId, $role);

        // Status list (id + code) so the view can deep-link single-status cards
        // to the matching list page with a pre-applied status_id filter.
        $statusList = Ticket::statuses();

        // Latest 5 tickets in the user's scope (reuse the existing list query
        // for each role — no dedicated query for this).
        if ($role === 'employee') {
            $recent = Ticket::findByCreator($userId, [], 5, 0);
        } elseif ($role === 'agent') {
            $recent = Ticket::findForAgent($userId, [], 5, 0);
        } else {
            $recent = Ticket::findAll([], 5, 0);
        }

        $pageTitle = Helpers::t('dashboard_title');
        require VIEWS_PATH . '/dashboard.php';
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Authenticate against LDAP (or the mock directory) and confirm the user
     * exists and is active in the database.
     *
     * @param string $employeeId
     * @param string $password
     * @return array<string,mixed>|null The user row on success, else null.
     */
    private function attemptLogin($employeeId, $password)
    {
        if ($employeeId === '' || $password === '') {
            return null;
        }

        if (!$this->ldapAuthenticate($employeeId, $password)) {
            error_log('Login failed: LDAP authentication rejected for employee_id=' . $employeeId);
            return null;
        }

        $user = User::findActiveByEmployeeId($employeeId);
        if ($user === null) {
            // Authenticated by directory but not provisioned/active locally.
            error_log('Login denied: no active local user for employee_id=' . $employeeId);
            return null;
        }

        return $user;
    }

    /**
     * Verify credentials against the directory. In 'mock' mode this checks
     * the static array in config/ldap.php; 'live' mode performs a real bind.
     *
     * @param string $employeeId
     * @param string $password
     * @return bool
     */
    private function ldapAuthenticate($employeeId, $password)
    {
        $config = require CONFIG_PATH . '/ldap.php';

        if (($config['mode'] ?? 'mock') === 'mock') {
            $mock = $config['mock_users'][$employeeId] ?? null;
            return $mock !== null && hash_equals((string) $mock['password'], $password);
        }

        // 'live' mode: bind against the real directory. The ldap extension is
        // expected to be enabled in the bank environment.
        $conn = @ldap_connect($config['host'], (int) $config['port']);
        if ($conn === false) {
            error_log('LDAP connect failed for host=' . $config['host']);
            return false;
        }
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        $rdn  = 'uid=' . $employeeId . ',' . $config['base_dn'];
        $bound = @ldap_bind($conn, $rdn, $password);
        ldap_unbind($conn);

        return $bound;
    }

    /**
     * Redirect relative to the front controller and stop.
     *
     * @param string $relative
     * @return void
     */
    private function redirect($relative)
    {
        header('Location: ' . BASE_URL . $relative);
        exit;
    }
}
