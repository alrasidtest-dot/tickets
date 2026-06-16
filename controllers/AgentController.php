<?php
/**
 * AgentController — the support agent's dashboard and ticket-processing view.
 *
 * Agents see the tickets assigned to them plus the unassigned ones (which they
 * may claim), and from the detail view they can claim a ticket, change its
 * status and post comments. Each status/assignment change is recorded in
 * ticket_history and raises a notification row (the model owns those writes
 * inside a single transaction — docs/BACKEND_GUIDE.md).
 *
 * Every method starts with Auth::require('agent'); row-level visibility and
 * modify rights are enforced through Ticket::canAccess / Ticket::canModify.
 */
require_once MODELS_PATH . '/Ticket.php';
require_once MODELS_PATH . '/Attachment.php';
require_once MODELS_PATH . '/Comment.php';

class AgentController
{
    /** Tickets shown per page in the dashboard list. */
    const PER_PAGE = 15;

    /**
     * Agent dashboard: the tickets assigned to the agent plus the unassigned
     * ones, with optional status/category/priority filters and pagination.
     *
     * @return void
     */
    public function dashboard()
    {
        Auth::require('agent');

        $agentId = (int) Auth::id();

        // Filters from the query string (ints only).
        $filters = [];
        foreach (['status_id', 'category_id', 'priority_id'] as $key) {
            if (isset($_GET[$key]) && ctype_digit((string) $_GET[$key]) && (int) $_GET[$key] > 0) {
                $filters[$key] = (int) $_GET[$key];
            }
        }

        // Pagination.
        $total   = Ticket::countForAgent($agentId, $filters);
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $pageNum = isset($_GET['p']) && ctype_digit((string) $_GET['p']) && (int) $_GET['p'] > 0
            ? (int) $_GET['p'] : 1;
        if ($pageNum > $pages) {
            $pageNum = $pages;
        }
        $offset = ($pageNum - 1) * self::PER_PAGE;

        $tickets    = Ticket::findForAgent($agentId, $filters, self::PER_PAGE, $offset);
        $categories = Ticket::categories();
        $priorities = Ticket::priorities();
        $statuses   = Ticket::statuses();

        $pageTitle = Helpers::t('agent_dashboard_title');
        require VIEWS_PATH . '/agent/dashboard.php';
    }

    /**
     * Agent ticket detail + processing. GET renders the ticket, its comments and
     * attachments, the claim / change-status controls and the comment form. POST
     * dispatches on the 'action' field (assign_me / change_status / add_comment),
     * then redirects (PRG).
     *
     * Visibility is the agent rule in Ticket::canAccess (assigned to them or
     * unassigned); status changes additionally require Ticket::canModify (the
     * ticket must already be theirs — an unassigned ticket is claimed first).
     *
     * @return void
     */
    public function viewTicket()
    {
        Auth::require('agent');

        $agentId = (int) Auth::id();
        $role    = (string) Auth::role();
        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;

        $ticket = $id > 0 ? Ticket::findDetailById($id) : null;
        // Not found, or not accessible to this agent: don't reveal which.
        if ($ticket === null || !Ticket::canAccess($ticket, $agentId, $role)) {
            $this->notFound();
        }

        $statuses = Ticket::statuses();
        $errors   = [];
        $old      = ['comment' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } else {
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'assign_me') {
                    // Claim only an unassigned ticket (or re-affirm one already
                    // ours); never steal a ticket from another agent.
                    if ($ticket['assigned_to'] === null
                        || (int) $ticket['assigned_to'] === $agentId) {
                        if (Ticket::assign($id, $agentId, $agentId)) {
                            $this->redirect('?page=agent_ticket_view&id=' . $id . '&assigned=1');
                        }
                        $errors['form'] = 'agent_action_error';
                    } else {
                        $errors['form'] = 'agent_not_allowed';
                    }
                } elseif ($action === 'change_status') {
                    if (!Ticket::canModify($ticket, $agentId, $role)) {
                        $errors['form'] = 'agent_claim_first';
                    } else {
                        $statusId = (string) ($_POST['status_id'] ?? '');
                        if (!$this->idIn($statusId, $statuses)) {
                            $errors['status'] = 'required_field';
                        } elseif (Ticket::changeStatus($id, (int) $statusId, $agentId)) {
                            $this->redirect('?page=agent_ticket_view&id=' . $id . '&status=1');
                        } else {
                            $errors['form'] = 'agent_action_error';
                        }
                    }
                } elseif ($action === 'add_comment') {
                    $old['comment'] = trim((string) ($_POST['comment'] ?? ''));
                    if ($old['comment'] === '') {
                        $errors['comment'] = 'required_field';
                    } elseif (Comment::create([
                        'ticket_id' => $id,
                        'user_id'   => $agentId,
                        'comment'   => $old['comment'],
                    ]) !== null) {
                        $this->redirect('?page=agent_ticket_view&id=' . $id . '&commented=1');
                    } else {
                        $errors['form'] = 'comment_save_error';
                    }
                }
            }

            // After a failed action, reload the row so the view reflects the
            // current state (e.g. assignment that partially changed).
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

        $canModify     = Ticket::canModify($ticket, $agentId, $role);
        $assigned      = isset($_GET['assigned']);
        $statusChanged = isset($_GET['status']);
        $commented     = isset($_GET['commented']);

        $pageTitle = (string) $ticket['ticket_number'];
        require VIEWS_PATH . '/agent/ticket_view.php';
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
     * Emit a generic 404 (used for both a missing ticket and one the agent may
     * not access, so existence never leaks) and stop.
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
