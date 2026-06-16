<?php
/**
 * Notification model — write/read access to the notifications table plus the
 * (mock) email dispatch that accompanies each notification.
 *
 * A notification stores a translation key (message_key) only — never ready-made
 * text — so the bell renders it via t() in the reader's current language at
 * display time, interpolating the ticket_number (docs/DATABASE.md).
 *
 * create() is a plain INSERT meant to run inside the caller's open transaction
 * (Ticket::changeStatus / assign, Comment::create); the matching e-mail is sent
 * after the caller commits, via sendEmail(), so a rolled-back action never
 * e-mails about something that did not happen.
 *
 * Prepared statements only; returns id / int / array / bool (no echo / HTML).
 */
require_once CORE_PATH . '/Mailer.php';

class Notification
{
    /**
     * Insert a notification row for a user about a ticket and return its id.
     * Safe to call inside an open transaction (uses the shared PDO connection).
     *
     * @param int    $userId     recipient
     * @param int    $ticketId
     * @param string $messageKey translation key, e.g. notif_status_changed
     * @return int The new notification id.
     */
    public static function create($userId, $ticketId, $messageKey)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications
                (user_id, ticket_id, message_key, is_read, email_sent, created_at)
             VALUES
                (:user_id, :ticket_id, :message_key, 0, 0, NOW())'
        );
        $stmt->execute([
            ':user_id'     => (int) $userId,
            ':ticket_id'   => (int) $ticketId,
            ':message_key' => (string) $messageKey,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Number of unread notifications for a user (the bell badge count).
     *
     * @param int $userId
     * @return int
     */
    public static function countUnread($userId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute([':user_id' => (int) $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * A user's most recent notifications, newest first, joined with the related
     * ticket_number (for the message) and id (for the link target).
     *
     * @param int $userId
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public static function recent($userId, $limit = 10)
    {
        $stmt = Database::connection()->prepare(
            'SELECT n.id, n.ticket_id, n.message_key, n.is_read, n.created_at,
                    t.ticket_number
             FROM notifications n
             JOIN tickets t ON t.id = n.ticket_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', (int) $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Mark a single notification read, but only when it belongs to the given
     * user (so one user can never flip another's notifications).
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function markRead($id, $userId)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE notifications SET is_read = 1
                 WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([':id' => (int) $id, ':user_id' => (int) $userId]);

            return true;
        } catch (Throwable $e) {
            error_log('Notification::markRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark every unread notification of a user as read.
     *
     * @param int $userId
     * @return bool
     */
    public static function markAllRead($userId)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE notifications SET is_read = 1
                 WHERE user_id = :user_id AND is_read = 0'
            );
            $stmt->execute([':user_id' => (int) $userId]);

            return true;
        } catch (Throwable $e) {
            error_log('Notification::markAllRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Look up a single notification by id (used to resolve its ticket for the
     * "open" redirect). Returns the row or null.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, user_id, ticket_id, message_key, is_read, email_sent
             FROM notifications WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Send the (mock) e-mail that accompanies a notification, then flag the row
     * email_sent = 1. Intended to run after the caller's transaction commits.
     *
     * Resolves the recipient's address and the ticket number, renders subject +
     * body from the default-language dictionary (the recipient's UI language is
     * not persisted per-user — docs/DATABASE.md — so a stable locale is used for
     * the audit log), and hands them to the Mailer (mock mode → log file).
     *
     * @param int $notificationId
     * @return bool Whether an e-mail was dispatched.
     */
    public static function sendEmail($notificationId)
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT n.id, n.message_key, n.email_sent,
                        u.email, u.full_name,
                        t.ticket_number
                 FROM notifications n
                 JOIN users   u ON u.id = n.user_id
                 JOIN tickets t ON t.id = n.ticket_id
                 WHERE n.id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => (int) $notificationId]);
            $row = $stmt->fetch();

            // Already sent, missing, or no address to send to: nothing to do.
            if ($row === false || (int) $row['email_sent'] === 1 || empty($row['email'])) {
                return false;
            }

            $subject = self::translateDefault('app_name');
            $body    = self::translateDefault(
                (string) $row['message_key'],
                ['ticket_number' => (string) $row['ticket_number']]
            );

            Mailer::send((string) $row['email'], $subject, $body);

            $mark = Database::connection()->prepare(
                'UPDATE notifications SET email_sent = 1 WHERE id = :id'
            );
            $mark->execute([':id' => (int) $notificationId]);

            return true;
        } catch (Throwable $e) {
            error_log('Notification::sendEmail failed: ' . $e->getMessage());
            return false;
        }
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Translate a key using the default-language dictionary (independent of the
     * acting user's session language), interpolating {param} placeholders.
     * Used for e-mail bodies, whose recipient is not the current request's user.
     *
     * @param string               $key
     * @param array<string,scalar> $params
     * @return string
     */
    private static function translateDefault($key, array $params = [])
    {
        static $dict = null;
        if ($dict === null) {
            $file = LANG_PATH . '/' . DEFAULT_LANG . '.php';
            $dict = is_file($file) ? (array) require $file : [];
        }

        $text = $dict[$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }
}
