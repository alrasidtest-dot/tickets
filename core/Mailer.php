<?php
/**
 * Mailer — outbound e-mail, with a mock transport for development.
 *
 * In mock mode (the only mode shipped with V1) no SMTP connection is made;
 * each message is appended to a log file instead of being delivered, so the
 * notification flow can be exercised end-to-end without a mail server.
 * Switching MODE to 'smtp' later is the single seam where a real transport
 * would be wired in.
 *
 * The log lives outside the document root (BASE_PATH/logs/mail.log) and the
 * directory is created on first use; nothing here is reachable by URL.
 */
class Mailer
{
    /** Active transport: 'mock' writes to a log, 'smtp' would send for real. */
    const MODE = 'mock';

    /**
     * "Send" a message. In mock mode the message is written to the mail log and
     * the method always reports success; a real transport would return whether
     * delivery was accepted.
     *
     * @param string $to      recipient address
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public static function send($to, $subject, $body)
    {
        if (self::MODE === 'mock') {
            return self::logMessage((string) $to, (string) $subject, (string) $body);
        }

        // No real SMTP transport in V1; treat as a no-op failure so callers can
        // tell a real send did not happen.
        return false;
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Append a human-readable record of one message to the mail log, creating
     * the log directory on first use. Returns whether the write succeeded.
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    private static function logMessage($to, $subject, $body)
    {
        $logDir = BASE_PATH . '/logs';
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            error_log('Mailer: could not create log directory ' . $logDir);
            return false;
        }

        $entry = '[' . date('Y-m-d H:i:s') . "] MOCK MAIL\n"
            . 'To: ' . $to . "\n"
            . 'Subject: ' . $subject . "\n"
            . 'Body: ' . $body . "\n"
            . str_repeat('-', 60) . "\n";

        return file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
