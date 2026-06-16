<?php
/**
 * Attachment model — write/read access for the ticket_attachments table.
 *
 * Used by Ticket::create for the first attachment at ticket creation
 * (comment_id = NULL) and, from phase 5 onward, for comment attachments
 * (comment_id set). File validation and the physical move happen in the
 * caller; this model only persists/reads the metadata row.
 *
 * Prepared statements only; returns id / array / null (no echo, no HTML).
 */
class Attachment
{
    /**
     * Insert an attachment metadata row.
     *
     * @param array{ticket_id:int,comment_id:?int,file_name:string,file_path:string,file_size:int,uploaded_by:int} $data
     * @return int The new attachment id.
     */
    public static function create(array $data)
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            'INSERT INTO ticket_attachments
                (ticket_id, comment_id, file_name, file_path, file_size, uploaded_by, uploaded_at)
             VALUES
                (:ticket_id, :comment_id, :file_name, :file_path, :file_size, :uploaded_by, NOW())'
        );

        $stmt->execute([
            ':ticket_id'   => (int) $data['ticket_id'],
            ':comment_id'  => isset($data['comment_id']) && $data['comment_id'] !== null
                ? (int) $data['comment_id'] : null,
            ':file_name'   => $data['file_name'],
            ':file_path'   => $data['file_path'],
            ':file_size'   => (int) $data['file_size'],
            ':uploaded_by' => (int) $data['uploaded_by'],
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Fetch a single attachment by id (used by the download endpoint in phase 5).
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, ticket_id, comment_id, file_name, file_path, file_size, uploaded_by, uploaded_at
             FROM ticket_attachments
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * All attachments of a ticket, oldest first. The detail view groups these
     * by comment_id: a NULL comment_id is a ticket-level attachment (added at
     * creation), a set comment_id belongs to that comment.
     *
     * @param int $ticketId
     * @return array<int,array<string,mixed>>
     */
    public static function findByTicket($ticketId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, ticket_id, comment_id, file_name, file_size, uploaded_at
             FROM ticket_attachments
             WHERE ticket_id = :ticket_id
             ORDER BY uploaded_at ASC, id ASC'
        );
        $stmt->execute([':ticket_id' => (int) $ticketId]);

        return $stmt->fetchAll();
    }
}
