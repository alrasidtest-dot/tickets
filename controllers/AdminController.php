<?php
/**
 * AdminController — the administrator's management pages.
 *
 * Two pages, each a single screen combining a list with add/edit forms,
 * following the Post/Redirect/Get pattern used elsewhere:
 *   - users()      : create / edit / enable-disable users (role + department).
 *   - categories() : create / edit / enable-disable ticket categories, plus
 *                    create / edit ticket priorities.
 *
 * Ticket reassignment (the third admin capability) lives on the existing ticket
 * detail page (?page=ticket_view) where an admin already has full access; it is
 * handled in TicketController and delegates to Ticket::assign (which records the
 * change in ticket_history).
 *
 * Every method starts with Auth::require('admin'); all input is validated here
 * before any model call, and every visible string comes from lang/ via t().
 */
require_once MODELS_PATH . '/User.php';
require_once MODELS_PATH . '/Ticket.php';

class AdminController
{
    /**
     * Rows loaded per page. The admin list tables are enhanced on the client
     * with live search / column sort / pagination (assets/js/app.js); we send a
     * generous slice and let the browser paginate it. The LIMIT stays as a
     * safety cap and no-JS fallback.
     */
    const PER_PAGE = 100;

    /** The roles an admin may assign to a user (top-down hierarchy order). */
    private static $roles = ['employee', 'agent', 'manager', 'admin'];

    /**
     * User management: list users (with role/active filters + pagination) and
     * an add/edit form. POST dispatches on 'action' (create / update /
     * toggle_active) then redirects (PRG).
     *
     * @return void
     */
    public function users()
    {
        Auth::require('admin');

        $departments = User::departments();

        $errors = [];
        $old    = $this->emptyUser();
        // Whether the form is in edit mode (id set) — drives the form title/action.
        $editing = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } else {
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'create') {
                    $old = $this->readUserInput();
                    $editing = false;
                    if ($this->validateUser($old, $departments, $errors, 0)) {
                        $id = User::create([
                            'employee_id'   => $old['employee_id'],
                            'full_name'     => $old['full_name'],
                            'email'         => $old['email'],
                            'role'          => $old['role'],
                            'department_id' => $old['department_id'] === '' ? null : (int) $old['department_id'],
                            'is_active'     => $old['is_active'] === '1' ? 1 : 0,
                        ]);
                        if ($id !== null) {
                            $this->redirect('?page=admin_users&saved=created');
                        }
                        $errors['form'] = 'admin_save_error';
                    }
                } elseif ($action === 'update') {
                    $old = $this->readUserInput();
                    $editing = true;
                    $id = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : 0;
                    $existing = $id > 0 ? User::findById($id) : null;
                    if ($existing === null) {
                        $errors['form'] = 'admin_user_not_found';
                    } elseif ($this->validateUser($old, $departments, $errors, $id)) {
                        $ok = User::update($id, [
                            'full_name'     => $old['full_name'],
                            'email'         => $old['email'],
                            'role'          => $old['role'],
                            'department_id' => $old['department_id'] === '' ? null : (int) $old['department_id'],
                            'is_active'     => $old['is_active'] === '1' ? 1 : 0,
                        ]);
                        if ($ok) {
                            $this->redirect('?page=admin_users&saved=updated');
                        }
                        $errors['form'] = 'admin_save_error';
                    }
                } elseif ($action === 'toggle_active') {
                    $id = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : 0;
                    $active = ($_POST['active'] ?? '') === '1';
                    if ($id > 0 && User::findById($id) !== null && User::setActive($id, $active)) {
                        $this->redirect('?page=admin_users&saved=toggled');
                    }
                    $errors['form'] = 'admin_save_error';
                }
            }
        } elseif (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
            // Load an existing user into the form for editing.
            $user = User::findById((int) $_GET['edit']);
            if ($user !== null) {
                $editing = true;
                $old = [
                    'id'            => (string) $user['id'],
                    'employee_id'   => (string) $user['employee_id'],
                    'full_name'     => (string) $user['full_name'],
                    'email'         => (string) $user['email'],
                    'role'          => (string) $user['role'],
                    'department_id' => $user['department_id'] !== null ? (string) $user['department_id'] : '',
                    'is_active'     => (int) $user['is_active'] === 1 ? '1' : '0',
                ];
            }
        }

        // Filters (role + active) from the query string.
        $filters = [];
        if (isset($_GET['role']) && in_array($_GET['role'], self::$roles, true)) {
            $filters['role'] = (string) $_GET['role'];
        }
        if (isset($_GET['active']) && ($_GET['active'] === '0' || $_GET['active'] === '1')) {
            $filters['is_active'] = (int) $_GET['active'];
        }

        // Pagination.
        $total   = User::countAll($filters);
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $pageNum = isset($_GET['p']) && ctype_digit((string) $_GET['p']) && (int) $_GET['p'] > 0
            ? (int) $_GET['p'] : 1;
        if ($pageNum > $pages) {
            $pageNum = $pages;
        }
        $offset = ($pageNum - 1) * self::PER_PAGE;

        $users  = User::all($filters, self::PER_PAGE, $offset);
        $roles  = self::$roles;
        $saved  = isset($_GET['saved']) ? (string) $_GET['saved'] : null;

        $pageTitle = Helpers::t('admin_users_title');
        require VIEWS_PATH . '/admin/users.php';
    }

    /**
     * Category & priority management: list both lookups and offer add/edit
     * forms. POST dispatches on 'action' (cat_create / cat_update /
     * cat_toggle / pri_create / pri_update) then redirects (PRG).
     *
     * @return void
     */
    public function categories()
    {
        Auth::require('admin');

        $departments = User::departments();

        $errors  = [];
        $catOld  = ['id' => '', 'name_ar' => '', 'name_en' => '', 'department_id' => ''];
        $priOld  = ['id' => '', 'name_ar' => '', 'name_en' => '', 'level' => '', 'sla_hours' => ''];
        $editCat = false;
        $editPri = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } else {
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'cat_create' || $action === 'cat_update') {
                    $editCat = $action === 'cat_update';
                    $catOld['id']            = (string) ($_POST['id'] ?? '');
                    $catOld['name_ar']       = trim((string) ($_POST['name_ar'] ?? ''));
                    $catOld['name_en']       = trim((string) ($_POST['name_en'] ?? ''));
                    $catOld['department_id'] = (string) ($_POST['department_id'] ?? '');

                    if ($this->validateNames($catOld, $errors)) {
                        // Department is optional; when supplied it must be real.
                        if ($catOld['department_id'] !== '' && !$this->idIn($catOld['department_id'], $departments)) {
                            $errors['department_id'] = 'required_field';
                        }
                    }
                    if (!$errors) {
                        $payload = [
                            'name_ar'       => $catOld['name_ar'],
                            'name_en'       => $catOld['name_en'],
                            'department_id' => $catOld['department_id'] === '' ? null : (int) $catOld['department_id'],
                        ];
                        if ($action === 'cat_create') {
                            $ok = Ticket::categoryCreate($payload) !== null;
                        } else {
                            $id = ctype_digit($catOld['id']) ? (int) $catOld['id'] : 0;
                            $ok = $id > 0 && Ticket::categoryUpdate($id, $payload);
                        }
                        if ($ok) {
                            $this->redirect('?page=admin_categories&saved=category');
                        }
                        $errors['form'] = 'admin_save_error';
                    }
                } elseif ($action === 'cat_toggle') {
                    $id = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : 0;
                    $active = ($_POST['active'] ?? '') === '1';
                    if ($id > 0 && Ticket::categorySetActive($id, $active)) {
                        $this->redirect('?page=admin_categories&saved=category');
                    }
                    $errors['form'] = 'admin_save_error';
                } elseif ($action === 'pri_toggle') {
                    $id = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : 0;
                    $active = ($_POST['active'] ?? '') === '1';
                    if ($id > 0 && Ticket::prioritySetActive($id, $active)) {
                        $this->redirect('?page=admin_categories&saved=priority');
                    }
                    $errors['form'] = 'admin_save_error';
                } elseif ($action === 'pri_create' || $action === 'pri_update') {
                    $editPri = $action === 'pri_update';
                    $priOld['id']        = (string) ($_POST['id'] ?? '');
                    $priOld['name_ar']   = trim((string) ($_POST['name_ar'] ?? ''));
                    $priOld['name_en']   = trim((string) ($_POST['name_en'] ?? ''));
                    $priOld['level']     = (string) ($_POST['level'] ?? '');
                    $priOld['sla_hours'] = (string) ($_POST['sla_hours'] ?? '');

                    if ($this->validatePriority($priOld, $errors)) {
                        $payload = [
                            'name_ar'   => $priOld['name_ar'],
                            'name_en'   => $priOld['name_en'],
                            'level'     => (int) $priOld['level'],
                            'sla_hours' => (int) $priOld['sla_hours'],
                        ];
                        if ($action === 'pri_create') {
                            $ok = Ticket::priorityCreate($payload) !== null;
                        } else {
                            $id = ctype_digit($priOld['id']) ? (int) $priOld['id'] : 0;
                            $ok = $id > 0 && Ticket::priorityUpdate($id, $payload);
                        }
                        if ($ok) {
                            $this->redirect('?page=admin_categories&saved=priority');
                        }
                        $errors['form'] = 'admin_save_error';
                    }
                }
            }
        } else {
            // Load a row into the matching form for editing.
            if (isset($_GET['edit_cat']) && ctype_digit((string) $_GET['edit_cat'])) {
                foreach (Ticket::categoriesAll() as $c) {
                    if ((int) $c['id'] === (int) $_GET['edit_cat']) {
                        $editCat = true;
                        $catOld = [
                            'id'            => (string) $c['id'],
                            'name_ar'       => (string) $c['name_ar'],
                            'name_en'       => (string) $c['name_en'],
                            'department_id' => $c['department_id'] !== null ? (string) $c['department_id'] : '',
                        ];
                        break;
                    }
                }
            }
            if (isset($_GET['edit_pri']) && ctype_digit((string) $_GET['edit_pri'])) {
                foreach (Ticket::prioritiesAll() as $p) {
                    if ((int) $p['id'] === (int) $_GET['edit_pri']) {
                        $editPri = true;
                        $priOld = [
                            'id'        => (string) $p['id'],
                            'name_ar'   => (string) $p['name_ar'],
                            'name_en'   => (string) $p['name_en'],
                            'level'     => (string) $p['level'],
                            'sla_hours' => (string) $p['sla_hours'],
                        ];
                        break;
                    }
                }
            }
        }

        $categories = Ticket::categoriesAll();
        $priorities = Ticket::prioritiesAll();
        $saved      = isset($_GET['saved']) ? (string) $_GET['saved'] : null;

        $pageTitle = Helpers::t('admin_categories_title');
        require VIEWS_PATH . '/admin/categories.php';
    }

    /**
     * Department management: list departments and an add/edit form. Departments
     * are the backbone of the manager routing model (categories belong to a
     * department; managers/technicians belong to a department), so the admin
     * needs to create and rename them here. POST dispatches on 'action'
     * (dept_create / dept_update) then redirects (PRG).
     *
     * @return void
     */
    public function departments()
    {
        Auth::require('admin');

        $errors  = [];
        $deptOld = ['id' => '', 'name_ar' => '', 'name_en' => ''];
        $editing = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } else {
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'dept_create' || $action === 'dept_update') {
                    $editing = $action === 'dept_update';
                    $deptOld['id']      = (string) ($_POST['id'] ?? '');
                    $deptOld['name_ar'] = trim((string) ($_POST['name_ar'] ?? ''));
                    $deptOld['name_en'] = trim((string) ($_POST['name_en'] ?? ''));

                    if ($this->validateNames($deptOld, $errors)) {
                        if ($action === 'dept_create') {
                            $ok = User::departmentCreate($deptOld) !== null;
                        } else {
                            $id = ctype_digit($deptOld['id']) ? (int) $deptOld['id'] : 0;
                            $ok = $id > 0 && User::departmentFindById($id) !== null
                                && User::departmentUpdate($id, $deptOld);
                        }
                        if ($ok) {
                            $this->redirect('?page=admin_departments&saved=department');
                        }
                        $errors['form'] = 'admin_save_error';
                    }
                }
            }
        } elseif (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
            $dept = User::departmentFindById((int) $_GET['edit']);
            if ($dept !== null) {
                $editing = true;
                $deptOld = [
                    'id'      => (string) $dept['id'],
                    'name_ar' => (string) $dept['name_ar'],
                    'name_en' => (string) $dept['name_en'],
                ];
            }
        }

        $departments = User::departments();
        $saved       = isset($_GET['saved']) ? (string) $_GET['saved'] : null;

        $pageTitle = Helpers::t('admin_departments_title');
        require VIEWS_PATH . '/admin/departments.php';
    }

    /**
     * All-tickets overview: every ticket in the system (no ownership scope),
     * with status/category/priority/agent filters and pagination, each row
     * linking to the shared ticket detail page (where an admin already has full
     * access, including reassignment). Read-only listing — no POST handling.
     *
     * @return void
     */
    public function tickets()
    {
        Auth::require('admin');

        $statuses   = Ticket::statuses();
        $categories = Ticket::categories();
        $priorities = Ticket::priorities();
        $agents     = User::assignees();

        // Filters from the query string.
        $filters = [];
        foreach (['status_id', 'category_id', 'priority_id'] as $key) {
            if (isset($_GET[$key]) && ctype_digit((string) $_GET[$key]) && (int) $_GET[$key] > 0) {
                $filters[$key] = (int) $_GET[$key];
            }
        }
        // Agent filter: a specific agent id, or the literal 'unassigned' set.
        $agentFilter = isset($_GET['agent']) ? (string) $_GET['agent'] : '';
        if ($agentFilter === 'unassigned') {
            $filters['unassigned'] = true;
        } elseif (ctype_digit($agentFilter) && (int) $agentFilter > 0) {
            $filters['assigned_to'] = (int) $agentFilter;
        }

        // Pagination.
        $total   = Ticket::countAll($filters);
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $pageNum = isset($_GET['p']) && ctype_digit((string) $_GET['p']) && (int) $_GET['p'] > 0
            ? (int) $_GET['p'] : 1;
        if ($pageNum > $pages) {
            $pageNum = $pages;
        }
        $offset = ($pageNum - 1) * self::PER_PAGE;

        $tickets = Ticket::findAll($filters, self::PER_PAGE, $offset);

        $pageTitle = Helpers::t('admin_tickets_title');
        require VIEWS_PATH . '/admin/tickets.php';
    }

    // ---- validation --------------------------------------------------------

    /**
     * Validate user-form input. On the create path ($id === 0) the employee_id
     * is required and must be unique; on update it is read-only (the existing
     * value) and skipped. Writes lang keys into $errors and returns whether the
     * input is valid.
     *
     * @param array<string,string>           $in
     * @param array<int,array<string,mixed>> $departments
     * @param array<string,string>           $errors
     * @param int                            $id  0 on create, else the edited id
     * @return bool
     */
    private function validateUser(array $in, array $departments, array &$errors, $id)
    {
        if ($id === 0) {
            if ($in['employee_id'] === '') {
                $errors['employee_id'] = 'required_field';
            } elseif (mb_strlen($in['employee_id']) > 50) {
                $errors['employee_id'] = 'admin_value_too_long';
            } elseif (User::existsEmployeeId($in['employee_id'], 0)) {
                $errors['employee_id'] = 'admin_employee_id_taken';
            }
        }

        if ($in['full_name'] === '') {
            $errors['full_name'] = 'required_field';
        } elseif (mb_strlen($in['full_name']) > 100) {
            $errors['full_name'] = 'admin_value_too_long';
        }

        if ($in['email'] === '') {
            $errors['email'] = 'required_field';
        } elseif (mb_strlen($in['email']) > 100 || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'admin_email_invalid';
        } elseif (User::existsEmail($in['email'], $id)) {
            $errors['email'] = 'admin_email_taken';
        }

        if (!in_array($in['role'], self::$roles, true)) {
            $errors['role'] = 'required_field';
        }

        // Department is optional; when supplied it must reference a real row.
        if ($in['department_id'] !== '' && !$this->idIn($in['department_id'], $departments)) {
            $errors['department_id'] = 'required_field';
        }

        return !$errors;
    }

    /**
     * Validate the shared name_ar / name_en pair (categories and priorities).
     *
     * @param array<string,string> $in
     * @param array<string,string> $errors
     * @return bool
     */
    private function validateNames(array $in, array &$errors)
    {
        if ($in['name_ar'] === '') {
            $errors['name_ar'] = 'required_field';
        } elseif (mb_strlen($in['name_ar']) > 100) {
            $errors['name_ar'] = 'admin_value_too_long';
        }
        if ($in['name_en'] === '') {
            $errors['name_en'] = 'required_field';
        } elseif (mb_strlen($in['name_en']) > 100) {
            $errors['name_en'] = 'admin_value_too_long';
        }

        return !$errors;
    }

    /**
     * Validate priority input: names plus a level in 1..3 and a positive SLA.
     *
     * @param array<string,string> $in
     * @param array<string,string> $errors
     * @return bool
     */
    private function validatePriority(array $in, array &$errors)
    {
        $this->validateNames($in, $errors);

        if (!ctype_digit($in['level']) || (int) $in['level'] < 1 || (int) $in['level'] > 3) {
            $errors['level'] = 'admin_level_invalid';
        }
        if (!ctype_digit($in['sla_hours']) || (int) $in['sla_hours'] < 1) {
            $errors['sla_hours'] = 'admin_sla_invalid';
        }

        return !$errors;
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Read and normalise the user form fields from $_POST.
     *
     * @return array<string,string>
     */
    private function readUserInput()
    {
        return [
            'id'            => (string) ($_POST['id'] ?? ''),
            'employee_id'   => trim((string) ($_POST['employee_id'] ?? '')),
            'full_name'     => trim((string) ($_POST['full_name'] ?? '')),
            'email'         => trim((string) ($_POST['email'] ?? '')),
            'role'          => (string) ($_POST['role'] ?? ''),
            'department_id' => (string) ($_POST['department_id'] ?? ''),
            'is_active'     => ($_POST['is_active'] ?? '') === '1' ? '1' : '0',
        ];
    }

    /**
     * A blank user form (active by default).
     *
     * @return array<string,string>
     */
    private function emptyUser()
    {
        return [
            'id'            => '',
            'employee_id'   => '',
            'full_name'     => '',
            'email'         => '',
            'role'          => '',
            'department_id' => '',
            'is_active'     => '1',
        ];
    }

    /**
     * Whether a submitted id (string) is a positive integer present in the
     * given lookup rows.
     *
     * @param string                         $value
     * @param array<int,array<string,mixed>> $rows
     * @return bool
     */
    private function idIn($value, array $rows)
    {
        if ($value === '' || !ctype_digit((string) $value)) {
            return false;
        }
        $id = (int) $value;
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return true;
            }
        }
        return false;
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
