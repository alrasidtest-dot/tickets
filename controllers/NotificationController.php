<?php
/**
 * NotificationController — the read side of the in-system notifications:
 * opening a single notification and clearing them all.
 *
 * Available to every authenticated role (employee / agent / admin); each action
 * operates strictly on the current user's own notifications. The unread badge
 * and the dropdown list itself are rendered by views/components/notifications_
 * dropdown.php inside the shared header, so there is no "list" action here.
 *
 * @see views/components/notifications_dropdown.php
 */
require_once MODELS_PATH . '/Notification.php';

class NotificationController
{
    /**
     * Open one notification: mark it read (only if it belongs to the current
     * user) and forward to the related ticket's detail page, choosing the view
     * that matches the user's role. Unknown / foreign / malformed ids fall back
     * to the dashboard, never revealing another user's notification.
     *
     * @return void
     */
    public function open()
    {
        Auth::requireAny(['employee', 'agent', 'admin']);

        $userId = (int) Auth::id();
        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;

        $notification = $id > 0 ? Notification::findById($id) : null;
        if ($notification === null || (int) $notification['user_id'] !== $userId) {
            $this->redirect('?page=dashboard');
        }

        // Mark read on open (idempotent; scoped to this user).
        Notification::markRead($id, $userId);

        // Route to the detail page appropriate for the role: agents use their
        // own processing view, employees and admins use the shared one.
        $ticketId = (int) $notification['ticket_id'];
        $target = Auth::role() === 'agent'
            ? '?page=agent_ticket_view&id=' . $ticketId
            : '?page=ticket_view&id=' . $ticketId;

        $this->redirect($target);
    }

    /**
     * Mark every one of the current user's notifications read, then redirect
     * back to the page the request came from (validated to a known page key) or
     * the dashboard. POST + CSRF only — this changes state.
     *
     * @return void
     */
    public function readAll()
    {
        Auth::requireAny(['employee', 'agent', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
            Notification::markAllRead((int) Auth::id());
        }

        // Return to the originating page when it is a real route, else dashboard.
        $back = (string) ($_POST['redirect'] ?? '');
        $page = $back !== '' && Router::has($back) ? $back : 'dashboard';

        $this->redirect('?page=' . $page);
    }

    // ---- internals ---------------------------------------------------------

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
