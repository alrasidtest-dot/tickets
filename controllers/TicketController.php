<?php
/**
 * TicketController — employee-facing ticket creation and the "my tickets" list.
 *
 * Only employees reach these actions (Auth::require('employee')). All input is
 * validated here before any model call; the actual create (ticket + optional
 * attachment + history) is delegated to Ticket::create, which owns the
 * transaction described in docs/BACKEND_GUIDE.md ("إنشاء تذكرة مع مرفق").
 */
require_once MODELS_PATH . '/Ticket.php';
require_once MODELS_PATH . '/Attachment.php';
require_once MODELS_PATH . '/Comment.php';
require_once MODELS_PATH . '/User.php';

class TicketController
{
    /**
     * Rows loaded per page. The list tables are enhanced on the client with
     * live search / column sort / pagination (assets/js/app.js), so we send a
     * generous slice in one request and let the browser paginate it; the
     * server LIMIT/OFFSET stays as a safety cap and no-JS fallback.
     */
    const PER_PAGE = 100;

    /**
     * Allowed upload types per docs/SECURITY_AUTH.md: extension => acceptable
     * real MIME types. docx/xlsx are zip containers, so some libmagic builds
     * report application/zip — accepted alongside the canonical Office MIME.
     */
    private static $allowedUpload = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'pdf'  => ['application/pdf'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
    ];

    /**
     * GET: render the new-ticket form. POST: validate CSRF + input and create
     * the ticket; on success redirect (PRG) to the list with a confirmation.
     *
     * @return void
     */
    public function newTicket()
    {
        Auth::require('employee');

        $categories = Ticket::categories();
        $priorities = Ticket::priorities();

        $errors = [];
        $old    = ['title' => '', 'description' => '', 'category_id' => '', 'priority_id' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } else {
                $old['title']       = trim((string) ($_POST['title'] ?? ''));
                $old['description'] = trim((string) ($_POST['description'] ?? ''));
                $old['category_id'] = (string) ($_POST['category_id'] ?? '');
                $old['priority_id'] = (string) ($_POST['priority_id'] ?? '');

                // ---- Text fields ----
                if ($old['title'] === '') {
                    $errors['title'] = 'required_field';
                } elseif (mb_strlen($old['title']) > 200) {
                    $errors['title'] = 'err_title_too_long';
                }
                if ($old['description'] === '') {
                    $errors['description'] = 'required_field';
                }
                if (!$this->idIn($old['category_id'], $categories)) {
                    $errors['category_id'] = 'required_field';
                }
                if (!$this->idIn($old['priority_id'], $priorities)) {
                    $errors['priority_id'] = 'required_field';
                }

                // ---- Optional attachment ----
                $upload = null;
                $file = $_FILES['attachment'] ?? null;
                if ($file !== null && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload = $this->validateUpload($file, $errors);
                }

                if (!$errors) {
                    $number = Ticket::create([
                        'title'       => $old['title'],
                        'description' => $old['description'],
                        'category_id' => (int) $old['category_id'],
                        'priority_id' => (int) $old['priority_id'],
                        'created_by'  => (int) Auth::id(),
                    ], $upload);

                    if ($number !== null) {
                        // Post/Redirect/Get so a refresh doesn't resubmit.
                        $this->redirect('?page=ticket_my&created=' . urlencode($number));
                    }

                    // Generic message only; technical detail stays in the log.
                    $errors['form'] = 'ticket_save_error';
                }
            }
        }

        $pageTitle = Helpers::t('ticket_new_title');
        require VIEWS_PATH . '/employee/new_ticket.php';
    }

    /**
     * List the current employee's own tickets, with optional status/category/
     * priority filters and pagination.
     *
     * @return void
     */
    public function myTickets()
    {
        Auth::require('employee');

        $userId = (int) Auth::id();

        // Filters from the query string (ints only).
        $filters = [];
        foreach (['status_id', 'category_id', 'priority_id'] as $key) {
            if (isset($_GET[$key]) && ctype_digit((string) $_GET[$key]) && (int) $_GET[$key] > 0) {
                $filters[$key] = (int) $_GET[$key];
            }
        }

        // Pagination.
        $total   = Ticket::countByCreator($userId, $filters);
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $pageNum = isset($_GET['p']) && ctype_digit((string) $_GET['p']) && (int) $_GET['p'] > 0
            ? (int) $_GET['p'] : 1;
        if ($pageNum > $pages) {
            $pageNum = $pages;
        }
        $offset = ($pageNum - 1) * self::PER_PAGE;

        $tickets    = Ticket::findByCreator($userId, $filters, self::PER_PAGE, $offset);
        $categories = Ticket::categories();
        $priorities = Ticket::priorities();
        $statuses   = Ticket::statuses();

        // Confirmation banner after a successful create (PRG).
        $createdNumber = isset($_GET['created']) ? (string) $_GET['created'] : null;

        $pageTitle = Helpers::t('ticket_my_title');
        require VIEWS_PATH . '/employee/my_tickets.php';
    }

    /**
     * Ticket detail page: the ticket, its comments and attachments, plus the
     * add-comment form (optional attachment). GET renders; POST adds a comment
     * then redirects (PRG).
     *
     * Role gate is broad (employee/agent/admin); the actual visibility is the
     * row-level RBAC check in Ticket::canAccess (owner, or agent/admin per the
     * matrix in docs/SECURITY_AUTH.md) — an admin has full access, so they may
     * open any ticket. A not-found / not-allowed ticket is never revealed:
     * employees go back to their list, other roles get a neutral 404.
     *
     * @return void
     */
    public function viewTicket()
    {
        Auth::requireAny(['employee', 'agent', 'admin']);

        $userId = (int) Auth::id();
        $role   = (string) Auth::role();
        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;

        $ticket = $id > 0 ? Ticket::findDetailById($id) : null;
        // Not found, or not accessible to this user: don't reveal which.
        if ($ticket === null || !Ticket::canAccess($ticket, $userId, $role)) {
            // The list page is employee-only; send other roles a neutral 404.
            if ($role === 'employee') {
                $this->redirect('?page=ticket_my');
            }
            $this->notFound();
        }

        $errors = [];
        $old    = ['comment' => ''];

        // Admins may reassign a ticket from this page. Within the department
        // structure the candidates are the ticket department's manager(s) and
        // technicians (docs/SECURITY_AUTH.md); the department is derived from the
        // ticket's category. Ticket::assign records the change in ticket_history.
        $ticketDeptId = isset($ticket['category_department_id']) && $ticket['category_department_id'] !== null
            ? (int) $ticket['category_department_id']
            : null;
        $agents = $role === 'admin' ? User::assignableForDepartment($ticketDeptId) : [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'login_csrf_error';
            } elseif (($_POST['action'] ?? '') === 'reassign' && $role === 'admin') {
                // Reassign to any agent (validated against the agent list).
                $agentId = (string) ($_POST['agent_id'] ?? '');
                if (!$this->idIn($agentId, $agents)) {
                    $errors['reassign'] = 'admin_agent_invalid';
                } elseif (Ticket::assign($id, (int) $agentId, $userId)) {
                    $this->redirect('?page=ticket_view&id=' . $id . '&reassigned=1');
                } else {
                    $errors['form'] = 'admin_reassign_error';
                }
            } else {
                $old['comment'] = trim((string) ($_POST['comment'] ?? ''));
                if ($old['comment'] === '') {
                    $errors['comment'] = 'required_field';
                }

                // Optional attachment, validated exactly as on the create form.
                $upload = null;
                $file = $_FILES['attachment'] ?? null;
                if ($file !== null && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload = $this->validateUpload($file, $errors);
                }

                if (!$errors) {
                    $commentId = Comment::create([
                        'ticket_id' => $id,
                        'user_id'   => $userId,
                        'comment'   => $old['comment'],
                    ], $upload);

                    if ($commentId !== null) {
                        // PRG so a refresh doesn't repost the comment.
                        $this->redirect('?page=ticket_view&id=' . $id . '&commented=1');
                    }
                    $errors['form'] = 'comment_save_error';
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

        // Split attachments: comment_id NULL = ticket-level (added at creation),
        // otherwise keyed by the comment it belongs to.
        $ticketAttachments  = [];
        $commentAttachments = [];
        foreach ($attachments as $a) {
            if ($a['comment_id'] === null) {
                $ticketAttachments[] = $a;
            } else {
                $commentAttachments[(int) $a['comment_id']][] = $a;
            }
        }

        $commented  = isset($_GET['commented']);
        $reassigned = isset($_GET['reassigned']);
        $isAdmin    = $role === 'admin';

        $pageTitle = (string) $ticket['ticket_number'];
        require VIEWS_PATH . '/employee/ticket_view.php';
    }

    /**
     * Secured attachment download: ?page=download&id={attachment_id}. The file
     * path is read from the DB (never from $_GET); access is checked against the
     * related ticket per docs/SECURITY_AUTH.md before any byte is streamed.
     * Missing file and "no permission" return the same 404 so existence never
     * leaks.
     *
     * @return void
     */
    public function download()
    {
        Auth::requireAny(['employee', 'agent', 'manager', 'admin']);

        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
        $attachment = $id > 0 ? Attachment::findById($id) : null;
        if ($attachment === null) {
            $this->notFound();
        }

        $ticket = Ticket::findDetailById((int) $attachment['ticket_id']);
        if ($ticket === null
            || !Ticket::canAccess($ticket, (int) Auth::id(), (string) Auth::role(), Auth::departmentId())) {
            $this->notFound();
        }

        // Resolve the stored relative path and confirm it stays inside
        // UPLOADS_PATH (defence in depth — file_path is system-generated).
        $base = realpath(UPLOADS_PATH);
        $full = realpath(UPLOADS_PATH . '/' . $attachment['file_path']);
        if ($base === false || $full === false
            || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0
            || !is_file($full)) {
            $this->notFound();
        }

        $this->streamFile($full, (string) $attachment['file_name']);
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Stream a file as an attachment download and stop. Clears any buffered
     * output first so only the file bytes reach the client.
     *
     * @param string $path         absolute path to an existing file
     * @param string $downloadName original (display) file name
     * @return void
     */
    private function streamFile($path, $downloadName)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);
        if ($mime === false) {
            $mime = 'application/octet-stream';
        }

        // filename* (RFC 5987) carries the real, possibly non-ASCII name; the
        // sanitised filename is the fallback for older clients.
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName);
        if ($fallback === null || $fallback === '') {
            $fallback = 'download';
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $fallback
            . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
        exit;
    }

    /**
     * Emit a generic 404 (used for both a missing attachment and an attachment
     * the user may not access) and stop.
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
            . Helpers::e(Helpers::t('attachment_not_found')) . '</p></body></html>';
        exit;
    }

    /**
     * Whether a submitted id (string from $_POST) is a positive integer that
     * exists in the given lookup rows.
     *
     * @param string                          $value
     * @param array<int,array<string,mixed>>  $rows
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
     * Validate an uploaded file against docs/SECURITY_AUTH.md (size, allowed
     * extension, real MIME) and return a ready-to-move spec, or null on error
     * (with the matching lang key written into $errors['attachment']).
     *
     * @param array<string,mixed> $file  one entry from $_FILES
     * @param array<string,string> $errors
     * @return array{tmp_name:string,stored_name:string,original_name:string,size:int}|null
     */
    private function validateUpload(array $file, array &$errors)
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file((string) $file['tmp_name'])) {
            $errors['attachment'] = 'err_attachment_upload';
            return null;
        }

        $size = (int) $file['size'];
        if ($size <= 0 || $size > UPLOAD_MAX_SIZE) {
            $errors['attachment'] = 'err_attachment_size';
            return null;
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::$allowedUpload[$ext])) {
            $errors['attachment'] = 'err_attachment_type';
            return null;
        }

        // Real MIME check (not just the file extension).
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file((string) $file['tmp_name']);
        if ($mime === false || !in_array($mime, self::$allowedUpload[$ext], true)) {
            $errors['attachment'] = 'err_attachment_type';
            return null;
        }

        return [
            'tmp_name'      => (string) $file['tmp_name'],
            // Random stored name (uniqid) keeping the validated extension.
            'stored_name'   => 'att_' . uniqid() . '.' . $ext,
            // Keep the original name (basename, length-capped) for display only.
            'original_name' => mb_substr(basename((string) $file['name']), 0, 255),
            'size'          => $size,
        ];
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
