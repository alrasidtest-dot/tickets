<?php
/**
 * Comment model — add/read ticket_comments, with an optional attachment that
 * reuses models/Attachment.php (comment_id set).
 *
 * Comment creation owns a single transaction so the comment, its optional
 * attachment, the tickets.updated_at bump and the ticket_history row are
 * stored atomically (docs/BACKEND_GUIDE.md). Prepared statements only; returns
 * id / array / null (no echo, no HTML — project rules).
 */
require_once MODELS_PATH . '/Attachment.php';
require_once MODELS_PATH . '/Notification.php';

class Comment
{
    /**
     * Add a comment (and, optionally, one attachment) to a ticket inside one
     * transaction: insert the comment, move + record the file when present,
     * bump tickets.updated_at, and log a 'comment' row in ticket_history.
     *
     * On any failure the transaction is rolled back and a file moved before the
     * failure is removed, keeping disk and database in sync.
     *
     * @param array{ticket_id:int,user_id:int,comment:string} $data
     * @param array{tmp_name:string,stored_name:string,original_name:string,size:int}|null $upload
     *        A pre-validated upload spec (validation in the controller), or null.
     * @return int|null The new comment id on success, or null on failure.
     */
    public static function create(array $data, array $upload = null)
    {
        $db = Database::connection();
        $ticketId  = (int) $data['ticket_id'];
        $userId    = (int) $data['user_id'];
        $movedPath = null;

        try {
            $db->beginTransaction();

            $insert = $db->prepare(
                'INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at)
                 VALUES (:ticket_id, :user_id, :comment, NOW())'
            );
            $insert->execute([
                ':ticket_id' => $ticketId,
                ':user_id'   => $userId,
                ':comment'   => (string) $data['comment'],
            ]);
            $commentId = (int) $db->lastInsertId();

            // Optional attachment, stored against this comment (comment_id set).
            if ($upload !== null) {
                $dir = UPLOADS_PATH . '/tickets/' . $ticketId;
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('Could not create upload directory');
                }

                $movedPath = $dir . '/' . $upload['stored_name'];
                if (!move_uploaded_file($upload['tmp_name'], $movedPath)) {
                    $movedPath = null; // nothing was actually moved
                    throw new RuntimeException('Could not move uploaded file');
                }

                // Path relative to UPLOADS_PATH so the download endpoint never
                // trusts a client-supplied path.
                $relative = 'tickets/' . $ticketId . '/' . $upload['stored_name'];
                Attachment::create([
                    'ticket_id'   => $ticketId,
                    'comment_id'  => $commentId,
                    'file_name'   => $upload['original_name'],
                    'file_path'   => $relative,
                    'file_size'   => (int) $upload['size'],
                    'uploaded_by' => $userId,
                ]);
            }

            // A new comment counts as activity on the ticket.
            $touch = $db->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = :id');
            $touch->execute([':id' => $ticketId]);

            // Audit trail: a comment was added.
            $history = $db->prepare(
                'INSERT INTO ticket_history
                    (ticket_id, user_id, action, old_value, new_value, created_at)
                 VALUES
                    (:ticket_id, :user_id, :action, NULL, NULL, NOW())'
            );
            $history->execute([
                ':ticket_id' => $ticketId,
                ':user_id'   => $userId,
                ':action'    => 'comment',
            ]);

            // Notify the other party on the ticket — its creator and its current
            // assignee — but never the author of this comment.
            $notifIds = [];
            foreach (self::recipients($db, $ticketId, $userId) as $recipientId) {
                $notifIds[] = Notification::create($recipientId, $ticketId, 'notif_comment');
            }

            $db->commit();

            // E-mail (mock transport) only after a successful commit.
            foreach ($notifIds as $notifId) {
                Notification::sendEmail($notifId);
            }
            return $commentId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($movedPath !== null && is_file($movedPath)) {
                @unlink($movedPath);
            }
            error_log('Comment::create failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The users to notify about a new comment on a ticket: its creator and its
     * current assignee, excluding the comment's author and any duplicate/NULL.
     *
     * @param PDO $db        the open connection (same transaction as the insert)
     * @param int $ticketId
     * @param int $authorId  the user who wrote the comment (never notified)
     * @return int[]
     */
    private static function recipients(PDO $db, $ticketId, $authorId)
    {
        $stmt = $db->prepare(
            'SELECT created_by, assigned_to FROM tickets WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $ticketId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return [];
        }

        $candidates = [(int) $row['created_by']];
        if ($row['assigned_to'] !== null) {
            $candidates[] = (int) $row['assigned_to'];
        }

        $recipients = [];
        foreach ($candidates as $id) {
            if ($id !== (int) $authorId && !in_array($id, $recipients, true)) {
                $recipients[] = $id;
            }
        }

        return $recipients;
    }

    /**
     * Comments on a ticket, oldest first, with the author's display name.
     *
     * @param int $ticketId
     * @return array<int,array<string,mixed>>
     */
    public static function findByTicket($ticketId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.comment, c.created_at, u.full_name AS author_name
             FROM ticket_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.ticket_id = :ticket_id
             ORDER BY c.created_at ASC, c.id ASC'
        );
        $stmt->execute([':ticket_id' => (int) $ticketId]);

        return $stmt->fetchAll();
    }
}
