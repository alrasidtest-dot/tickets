<?php
/**
 * ManagerController — the department manager's queue and ticket-processing view.
 *
 * A department manager sees every ticket whose category belongs to their
 * department (Ticket::findForManager) and, from the detail view, can dispatch a
 * ticket to one of their department's technicians, change its status and post
 * comments — the manager both routes and processes (docs/SECURITY_AUTH.md).
 *
 * Every method starts with Auth::require('manager'); row-level visibility and
 * modify rights are enforced through Ticket::canAccess / Ticket::canModify with
 * the manager's department id (Auth::departmentId()). Each status/assignment
 * change is recorded in ticket_history and raises a notification (the model owns
 * those writes inside a single transaction — docs/BACKEND_GUIDE.md).
 */
require_once MODELS_PATH . '/Ticket.php';
require_once MODELS_PATH . '/Attachment.php';
require_once MODELS_PATH . '/Comment.php';
require_once MODELS_PATH . '/User.php';

class ManagerController
{
    /**
     * Rows loaded per page. The dashboard table is enhanced on the client with
     * live search / column sort / pagination (assets/js/app.js); we send a
     * generous slice and let the browser paginate it. The LIMIT stays as a
     * safety cap and no-JS fallback.
     */
    const PER_PAGE = 100;

    /**
     * Manager dashboard: every ticket in the manager's department, with optional
     * status/category/priority filters and pagination.
     *
     * @return void
     */
    public function dashboard()
    {
        Auth::require('manager');

        $deptId = Auth::departmentId();

        // Filters from the query string (ints only).
        $filters = [];
        foreach (['status_id', 'category_id', 'priority_id'] as $key) {
            if (isset($_GET[$key]) && ctype_digit((string) $_GET[$key]) && (int) $_GET[$key] > 0) {
                $filters[$key] = (int) $_GET[$key];
            }
        }

        // A manager with no department has an empty scope.
        if ($deptId === null) {
            $total   = 0;
            $tickets = [];
        } else {
            $total   = Ticket::countForManager($deptId, $filters);
        }

        // Pagination.
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $pageNum = isset($_GET['p']) && ctype_digit((string) $_GET['p']) && (int) $_GET['p'] > 0
            ? (int) $_GET['p'] : 1;
        if ($pageNum > $pages) {
            $pageNum = $pages;
        }
        $offset = ($pageNum - 1) * self::PER_PAGE;

        if ($deptId !== null) {
            $tickets = Ticket::findForManager($deptId, $filters, self::PER_PAGE, $offset);
        }

        $categories = Ticket::categories();
        $priorities = Ticket::priorities();
        $statuses   = Ticket::statuses();

        $pageTitle = Helpers::t('manager_dashboard_title');
        require VIEWS_PATH . '/manager/dashboard.php';
    }

    /**
     * Manager ticket detail + processing. GET renders the ticket, its comments
     * and attachments, the assign / change-status controls and the comment form.
     * POST dispatches on the 'action' field (assign / change_status /
     * add_comment), then redirects (PRG).
     *
     * Visibility and modify rights are the manager department scope in
     * Ticket::canAccess / Ticket::canModify.
     *
     * @return void
     */
    public function viewTicket()
    {
        Auth::require('manager');

        $managerId = (int) Auth::id();
        $role      = (string) Auth::role();
        $deptId    = Auth::departmentId();
        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;

        $ticket = $id > 0 ? Ticket::findDetailById($id) : null;
        // Not found, or not in this manager's department: don't reveal which.
        if ($ticket === null || !Ticket::canAccess($ticket, $managerId, $role, $deptId)) {
            $this->notFound();
        }

        // The technicians this manager may dispatch the ticket to.
        $technicians = User::techniciansByDepartment($deptId);
        $statuses    = Ticket::statuses();
        $errors      = [];
        $old         = ['comment' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } else {
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'assign') {
                    // Dispatch to one of the department's technicians only.
                    $agentId = (string) ($_POST['agent_id'] ?? '');
                    if (!$this->idIn($agentId, $technicians)) {
                        $errors['assign'] = 'manager_technician_invalid';
                    } elseif (Ticket::assign($id, (int) $agentId, $managerId)) {
                        $this->redirect('?page=manager_ticket_view&id=' . $id . '&assigned=1');
                    } else {
                        $errors['form'] = 'manager_action_error';
                    }
                } elseif ($action === 'change_status') {
                    $statusId = (string) ($_POST['status_id'] ?? '');
                    if (!$this->idIn($statusId, $statuses)) {
                        $errors['status'] = 'required_field';
                    } elseif (Ticket::changeStatus($id, (int) $statusId, $managerId)) {
                        $this->redirect('?page=manager_ticket_view&id=' . $id . '&status=1');
                    } else {
                        $errors['form'] = 'manager_action_error';
                    }
                } elseif ($action === 'add_comment') {
                    $old['comment'] = trim((string) ($_POST['comment'] ?? ''));
                    if ($old['comment'] === '') {
                        $errors['comment'] = 'required_field';
                    } elseif (Comment::create([
                        'ticket_id' => $id,
                        'user_id'   => $managerId,
                        'comment'   => $old['comment'],
                    ]) !== null) {
                        $this->redirect('?page=manager_ticket_view&id=' . $id . '&commented=1');
                    } else {
                        $errors['form'] = 'comment_save_error';
                    }
                }
            }

            // Reload the row so a failed/successful action reflects current state.
            $refreshed = Ticket::findDetailById($id);
            if ($refreshed !== null) {
                $ticket = $refreshed;
            }
        }

        $comments    = Comment::findByTicket($id);
        $attachments = Attachment::findByTicket($id);

        // Split attachments: comment_id NULL = ticket-level, else keyed by comment.
        $ticketAttachments  = [];
        $commentAttachments = [];
        foreach ($attachments as $a) {
            if ($a['comment_id'] === null) {
                $ticketAttachments[] = $a;
            } else {
                $commentAttachments[(int) $a['comment_id']][] = $a;
            }
        }

        $assigned      = isset($_GET['assigned']);
        $statusChanged = isset($_GET['status']);
        $commented     = isset($_GET['commented']);

        $pageTitle = (string) $ticket['ticket_number'];
        require VIEWS_PATH . '/manager/ticket_view.php';
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Whether a submitted id (string from $_POST) is a positive integer that
     * exists in the given lookup rows.
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
     * Emit a generic 404 (used for both a missing ticket and one outside the
     * manager's department, so existence never leaks) and stop.
     *
     * @return void
     */
    private function notFound()
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        $lang = Helpers::lang();
        $dir  = $lang === 'ar' ? 'rtl' : 'ltr';
        echo '<!DOCTYPE html><html lang="' . Helpers::e($lang) . '" dir="' . $dir . '"><head>'
            . '<meta charset="utf-8"><title>404</title></head><body><p>'
            . Helpers::e(Helpers::t('ticket_not_found')) . '</p></body></html>';
        exit;
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
