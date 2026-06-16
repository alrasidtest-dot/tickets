<?php
/**
 * Ticket model — create + read access for the tickets table and the small
 * lookup tables the ticket form depends on (categories, priorities, statuses).
 *
 * All queries use PDO prepared statements; the model returns ticket numbers,
 * ids, arrays, bool or null only (no echo / HTML — project rules). Ticket
 * creation owns its own DB transaction so a ticket and its optional first
 * attachment are stored atomically (docs/BACKEND_GUIDE.md — "إنشاء تذكرة مع مرفق").
 */
require_once MODELS_PATH . '/Attachment.php';
require_once MODELS_PATH . '/Notification.php';

class Ticket
{
    /** Max attempts to settle a daily ticket_number race before giving up. */
    const MAX_NUMBER_ATTEMPTS = 3;

    /**
     * Create a ticket (status = new) and, optionally, its first attachment,
     * inside a single transaction, and record a 'create' row in ticket_history.
     *
     * On any failure the whole transaction is rolled back — and a file that was
     * already moved is removed — so a ticket is never left without the
     * attachment that was meant to accompany it (and vice-versa).
     *
     * @param array{title:string,description:string,category_id:int,priority_id:int,created_by:int} $data
     * @param array{tmp_name:string,stored_name:string,original_name:string,size:int}|null $upload
     *        A pre-validated upload spec (validation done in the controller), or
     *        null when no file was attached.
     * @return string|null The new ticket_number on success, or null on failure.
     */
    public static function create(array $data, array $upload = null)
    {
        $db = Database::connection();
        $movedPath = null;

        try {
            $db->beginTransaction();

            $statusId  = self::newStatusId();
            $ticketId  = null;
            $ticketNum = null;

            // Insert the ticket, regenerating the daily sequence on a UNIQUE
            // collision (concurrent create). A duplicate-key error aborts only
            // the failing statement, not the surrounding InnoDB transaction.
            $insert = $db->prepare(
                'INSERT INTO tickets
                    (ticket_number, title, description, category_id, priority_id,
                     status_id, created_by, assigned_to, created_at, updated_at)
                 VALUES
                    (:ticket_number, :title, :description, :category_id, :priority_id,
                     :status_id, :created_by, NULL, NOW(), NOW())'
            );

            for ($attempt = 1; $attempt <= self::MAX_NUMBER_ATTEMPTS; $attempt++) {
                $ticketNum = self::nextTicketNumber();
                try {
                    $insert->execute([
                        ':ticket_number' => $ticketNum,
                        ':title'         => $data['title'],
                        ':description'   => $data['description'],
                        ':category_id'   => (int) $data['category_id'],
                        ':priority_id'   => (int) $data['priority_id'],
                        ':status_id'     => $statusId,
                        ':created_by'    => (int) $data['created_by'],
                    ]);
                    $ticketId = (int) $db->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    // SQLSTATE 23000 = integrity constraint violation (duplicate
                    // ticket_number). Retry with a freshly computed sequence.
                    if ($e->getCode() === '23000' && $attempt < self::MAX_NUMBER_ATTEMPTS) {
                        continue;
                    }
                    throw $e;
                }
            }

            if ($ticketId === null) {
                throw new RuntimeException('Could not allocate a unique ticket number');
            }

            // History: ticket created.
            self::addHistory($ticketId, (int) $data['created_by'], 'create', null, $ticketNum);

            // Optional first attachment (comment_id stays NULL at creation).
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

                // Store a path relative to UPLOADS_PATH so the download endpoint
                // can resolve it without trusting any client-supplied path.
                $relative = 'tickets/' . $ticketId . '/' . $upload['stored_name'];
                Attachment::create([
                    'ticket_id'   => $ticketId,
                    'comment_id'  => null,
                    'file_name'   => $upload['original_name'],
                    'file_path'   => $relative,
                    'file_size'   => (int) $upload['size'],
                    'uploaded_by' => (int) $data['created_by'],
                ]);
            }

            $db->commit();
            return $ticketNum;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Remove a file that was moved before the failure (keeps disk in
            // sync with the rolled-back transaction).
            if ($movedPath !== null && is_file($movedPath)) {
                @unlink($movedPath);
            }
            error_log('Ticket::create failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tickets created by a given user, newest first, with the joined lookup
     * fields the list view needs. Supports status/category/priority filters
     * and LIMIT/OFFSET pagination.
     *
     * @param int                                                              $userId
     * @param array{status_id?:int,category_id?:int,priority_id?:int}          $filters
     * @param int                                                              $limit
     * @param int                                                              $offset
     * @return array<int,array<string,mixed>>
     */
    public static function findByCreator($userId, array $filters = [], $limit = 10, $offset = 0)
    {
        [$where, $params] = self::buildFilter((int) $userId, $filters);

        $sql = 'SELECT t.id, t.ticket_number, t.title, t.created_at, t.updated_at,
                       s.code  AS status_code,
                       p.level AS priority_level,
                       c.name_ar AS category_name_ar,
                       c.name_en AS category_name_en,
                       EXISTS (SELECT 1 FROM ticket_attachments a WHERE a.ticket_id = t.id) AS has_attachment
                FROM tickets t
                JOIN ticket_statuses   s ON s.id = t.status_id
                JOIN ticket_priorities p ON p.id = t.priority_id
                JOIN ticket_categories c ON c.id = t.category_id
                ' . $where . '
                ORDER BY t.created_at DESC, t.id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count of tickets created by a user, honouring the same filters as
     * findByCreator (used for pagination).
     *
     * @param int                                                     $userId
     * @param array{status_id?:int,category_id?:int,priority_id?:int} $filters
     * @return int
     */
    public static function countByCreator($userId, array $filters = [])
    {
        [$where, $params] = self::buildFilter((int) $userId, $filters);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM tickets t ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Active categories for the ticket form / filters.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function categories()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name_ar, name_en FROM ticket_categories WHERE is_active = 1 ORDER BY id'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Priorities (ordered by level: urgent, medium, low) for form / filters.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function priorities()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, level FROM ticket_priorities WHERE is_active = 1 ORDER BY level'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Statuses for the list filter.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function statuses()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code FROM ticket_statuses ORDER BY id'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ---- Admin: category & priority management (phase 7) -------------------

    /**
     * All categories (active and inactive), for the admin management list.
     * Distinct from categories(), which only returns the active ones for the
     * ticket form / filters.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function categoriesAll()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name_ar, name_en, is_active FROM ticket_categories ORDER BY id'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * All priorities (ordered by level), for the admin management list.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function prioritiesAll()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name_ar, name_en, level, sla_hours, is_active
             FROM ticket_priorities ORDER BY level'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Create a ticket category (active by default).
     *
     * @param array{name_ar:string,name_en:string} $data
     * @return int|null The new category id, or null on failure.
     */
    public static function categoryCreate(array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO ticket_categories (name_ar, name_en, is_active)
                 VALUES (:name_ar, :name_en, 1)'
            );
            $stmt->execute([
                ':name_ar' => (string) $data['name_ar'],
                ':name_en' => (string) $data['name_en'],
            ]);

            return (int) Database::connection()->lastInsertId();
        } catch (Throwable $e) {
            error_log('Ticket::categoryCreate failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a category's names (its active flag is toggled separately).
     *
     * @param int                                   $id
     * @param array{name_ar:string,name_en:string}  $data
     * @return bool
     */
    public static function categoryUpdate($id, array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE ticket_categories SET name_ar = :name_ar, name_en = :name_en
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name_ar' => (string) $data['name_ar'],
                ':name_en' => (string) $data['name_en'],
                ':id'      => (int) $id,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('Ticket::categoryUpdate failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable or disable a category (soft toggle of is_active). A disabled
     * category disappears from new-ticket forms but existing tickets keep it.
     *
     * @param int  $id
     * @param bool $active
     * @return bool
     */
    public static function categorySetActive($id, $active)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE ticket_categories SET is_active = :active WHERE id = :id'
            );
            $stmt->execute([':active' => $active ? 1 : 0, ':id' => (int) $id]);

            return true;
        } catch (Throwable $e) {
            error_log('Ticket::categorySetActive failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a ticket priority.
     *
     * @param array{name_ar:string,name_en:string,level:int,sla_hours:int} $data
     * @return int|null The new priority id, or null on failure.
     */
    public static function priorityCreate(array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO ticket_priorities (name_ar, name_en, level, sla_hours)
                 VALUES (:name_ar, :name_en, :level, :sla_hours)'
            );
            $stmt->execute([
                ':name_ar'   => (string) $data['name_ar'],
                ':name_en'   => (string) $data['name_en'],
                ':level'     => (int) $data['level'],
                ':sla_hours' => (int) $data['sla_hours'],
            ]);

            return (int) Database::connection()->lastInsertId();
        } catch (Throwable $e) {
            error_log('Ticket::priorityCreate failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a priority (name, level and SLA hours). The active flag is toggled
     * separately via prioritySetActive.
     *
     * @param int                                                          $id
     * @param array{name_ar:string,name_en:string,level:int,sla_hours:int} $data
     * @return bool
     */
    public static function priorityUpdate($id, array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE ticket_priorities SET
                    name_ar = :name_ar, name_en = :name_en,
                    level = :level, sla_hours = :sla_hours
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name_ar'   => (string) $data['name_ar'],
                ':name_en'   => (string) $data['name_en'],
                ':level'     => (int) $data['level'],
                ':sla_hours' => (int) $data['sla_hours'],
                ':id'        => (int) $id,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('Ticket::priorityUpdate failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable or disable a priority (soft toggle of is_active). A disabled
     * priority disappears from new-ticket forms but existing tickets keep it.
     *
     * @param int  $id
     * @param bool $active
     * @return bool
     */
    public static function prioritySetActive($id, $active)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE ticket_priorities SET is_active = :active WHERE id = :id'
            );
            $stmt->execute([':active' => $active ? 1 : 0, ':id' => (int) $id]);

            return true;
        } catch (Throwable $e) {
            error_log('Ticket::prioritySetActive failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Full detail of a single ticket for the detail view: the ticket row plus
     * the joined lookup fields and the creator/assignee display names. Returns
     * null when no ticket has that id.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findDetailById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id, t.ticket_number, t.title, t.description,
                    t.created_by, t.assigned_to, t.created_at, t.updated_at, t.closed_at,
                    s.code  AS status_code,
                    p.level AS priority_level,
                    c.name_ar AS category_name_ar,
                    c.name_en AS category_name_en,
                    cu.full_name AS creator_name,
                    au.full_name AS assignee_name
             FROM tickets t
             JOIN ticket_statuses   s  ON s.id  = t.status_id
             JOIN ticket_priorities p  ON p.id  = t.priority_id
             JOIN ticket_categories c  ON c.id  = t.category_id
             JOIN users             cu ON cu.id = t.created_by
             LEFT JOIN users        au ON au.id = t.assigned_to
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Tickets visible to an agent: the ones assigned to them plus every
     * unassigned ticket (which they may claim) — docs/BACKEND_GUIDE.md. Newest
     * unassigned tickets surface first (so they can be picked up), then by
     * priority and recency. Supports the same status/category/priority filters
     * and LIMIT/OFFSET pagination as the employee list.
     *
     * @param int                                                     $agentId
     * @param array{status_id?:int,category_id?:int,priority_id?:int} $filters
     * @param int                                                     $limit
     * @param int                                                     $offset
     * @return array<int,array<string,mixed>>
     */
    public static function findForAgent($agentId, array $filters = [], $limit = 15, $offset = 0)
    {
        [$where, $params] = self::buildAgentFilter((int) $agentId, $filters);

        $sql = 'SELECT t.id, t.ticket_number, t.title, t.created_at, t.updated_at, t.assigned_to,
                       s.code  AS status_code,
                       p.level AS priority_level,
                       c.name_ar AS category_name_ar,
                       c.name_en AS category_name_en,
                       cu.full_name AS creator_name,
                       EXISTS (SELECT 1 FROM ticket_attachments a WHERE a.ticket_id = t.id) AS has_attachment
                FROM tickets t
                JOIN ticket_statuses   s  ON s.id  = t.status_id
                JOIN ticket_priorities p  ON p.id  = t.priority_id
                JOIN ticket_categories c  ON c.id  = t.category_id
                JOIN users             cu ON cu.id = t.created_by
                ' . $where . '
                ORDER BY (t.assigned_to IS NULL) DESC, p.level ASC, t.created_at DESC, t.id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count of tickets visible to an agent (same scope/filters as
     * findForAgent), used for pagination.
     *
     * @param int                                                     $agentId
     * @param array{status_id?:int,category_id?:int,priority_id?:int} $filters
     * @return int
     */
    public static function countForAgent($agentId, array $filters = [])
    {
        [$where, $params] = self::buildAgentFilter((int) $agentId, $filters);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM tickets t ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // ---- Admin: all-tickets overview --------------------------------------

    /**
     * Every ticket (no ownership scope), newest first, with the joined lookup
     * fields the admin overview list needs. Supports status/category/priority
     * and assignee filters plus LIMIT/OFFSET pagination.
     *
     * @param array{status_id?:int,category_id?:int,priority_id?:int,assigned_to?:int,unassigned?:bool} $filters
     * @param int                                                                                        $limit
     * @param int                                                                                        $offset
     * @return array<int,array<string,mixed>>
     */
    public static function findAll(array $filters = [], $limit = 20, $offset = 0)
    {
        [$where, $params] = self::buildAllFilter($filters);

        $sql = 'SELECT t.id, t.ticket_number, t.title, t.created_at, t.updated_at, t.assigned_to,
                       s.code  AS status_code,
                       p.level AS priority_level,
                       c.name_ar AS category_name_ar,
                       c.name_en AS category_name_en,
                       cu.full_name AS creator_name,
                       au.full_name AS assignee_name,
                       EXISTS (SELECT 1 FROM ticket_attachments a WHERE a.ticket_id = t.id) AS has_attachment
                FROM tickets t
                JOIN ticket_statuses   s  ON s.id  = t.status_id
                JOIN ticket_priorities p  ON p.id  = t.priority_id
                JOIN ticket_categories c  ON c.id  = t.category_id
                JOIN users             cu ON cu.id = t.created_by
                LEFT JOIN users        au ON au.id = t.assigned_to
                ' . $where . '
                ORDER BY t.created_at DESC, t.id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count of all tickets honouring the same filters as findAll (pagination).
     *
     * @param array{status_id?:int,category_id?:int,priority_id?:int,assigned_to?:int,unassigned?:bool} $filters
     * @return int
     */
    public static function countAll(array $filters = [])
    {
        [$where, $params] = self::buildAllFilter($filters);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM tickets t ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Change a ticket's status inside one transaction: update the status,
     * maintain closed_at (set when entering 'closed', cleared otherwise —
     * docs/BACKEND_GUIDE.md), bump updated_at, log a 'status_change' row in
     * ticket_history and create a notification row for the ticket's creator.
     *
     * A no-op (same status) commits without writing history/notification.
     *
     * @param int $ticketId
     * @param int $newStatusId
     * @param int $userId      the acting user (agent/admin)
     * @return bool
     */
    public static function changeStatus($ticketId, $newStatusId, $userId)
    {
        $db = Database::connection();

        try {
            $db->beginTransaction();

            // Lock the row and read the current status + creator.
            $cur = $db->prepare(
                'SELECT t.status_id, s.code AS status_code, t.created_by
                 FROM tickets t
                 JOIN ticket_statuses s ON s.id = t.status_id
                 WHERE t.id = :id
                 FOR UPDATE'
            );
            $cur->execute([':id' => (int) $ticketId]);
            $row = $cur->fetch();
            if ($row === false) {
                throw new RuntimeException('Ticket not found');
            }

            $oldStatusId = (int) $row['status_id'];
            $oldCode     = (string) $row['status_code'];
            $createdBy   = (int) $row['created_by'];

            // Resolve the target status code (and validate it exists).
            $ns = $db->prepare('SELECT code FROM ticket_statuses WHERE id = :id LIMIT 1');
            $ns->execute([':id' => (int) $newStatusId]);
            $newCode = $ns->fetchColumn();
            if ($newCode === false) {
                throw new RuntimeException('Unknown status');
            }
            $newCode = (string) $newCode;

            // Nothing to do when the status is unchanged.
            if ((int) $newStatusId === $oldStatusId) {
                $db->commit();
                return true;
            }

            // closed_at follows the 'closed' transition only (NULL otherwise).
            if ($newCode === 'closed') {
                $upd = $db->prepare(
                    'UPDATE tickets SET status_id = :sid, closed_at = NOW(), updated_at = NOW()
                     WHERE id = :id'
                );
            } else {
                $upd = $db->prepare(
                    'UPDATE tickets SET status_id = :sid, closed_at = NULL, updated_at = NOW()
                     WHERE id = :id'
                );
            }
            $upd->execute([':sid' => (int) $newStatusId, ':id' => (int) $ticketId]);

            self::addHistory($ticketId, $userId, 'status_change', $oldCode, $newCode);
            // Notify the ticket's creator (unless they are the one acting).
            $notifId = $createdBy !== (int) $userId
                ? self::addNotification($createdBy, (int) $ticketId, 'notif_status_changed')
                : null;

            $db->commit();

            // E-mail is sent only after a successful commit (mock transport).
            if ($notifId !== null) {
                Notification::sendEmail($notifId);
            }
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Ticket::changeStatus failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign a ticket to a user inside one transaction: update assigned_to,
     * bump updated_at, log an 'assign' row in ticket_history (old/new assignee
     * names) and create a notification row for the ticket's creator.
     *
     * A no-op (already assigned to that user) commits without side effects.
     *
     * @param int $ticketId
     * @param int $assigneeId the user the ticket is assigned to
     * @param int $actorId    the acting user (the agent claiming it / an admin)
     * @return bool
     */
    public static function assign($ticketId, $assigneeId, $actorId)
    {
        $db = Database::connection();

        try {
            $db->beginTransaction();

            $cur = $db->prepare(
                'SELECT assigned_to, created_by FROM tickets WHERE id = :id FOR UPDATE'
            );
            $cur->execute([':id' => (int) $ticketId]);
            $row = $cur->fetch();
            if ($row === false) {
                throw new RuntimeException('Ticket not found');
            }

            $oldAssignee = $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null;
            $createdBy   = (int) $row['created_by'];
            $newAssignee = (int) $assigneeId;

            // Already assigned to that user: nothing to record.
            if ($oldAssignee === $newAssignee) {
                $db->commit();
                return true;
            }

            $upd = $db->prepare(
                'UPDATE tickets SET assigned_to = :aid, updated_at = NOW() WHERE id = :id'
            );
            $upd->execute([':aid' => $newAssignee, ':id' => (int) $ticketId]);

            // History keeps human-readable assignee names (VARCHAR(100)).
            $oldName = $oldAssignee !== null ? self::userName($oldAssignee) : null;
            $newName = self::userName($newAssignee);
            self::addHistory($ticketId, $actorId, 'assign', $oldName, $newName);
            // Notify the ticket's creator (unless they are the one acting), and
            // the newly assigned agent (unless they assigned the ticket to
            // themselves — the common "claim" case).
            $notifIds = [];
            if ($createdBy !== (int) $actorId) {
                $notifIds[] = self::addNotification($createdBy, (int) $ticketId, 'notif_assigned');
            }
            if ($newAssignee !== (int) $actorId && $newAssignee !== $createdBy) {
                $notifIds[] = self::addNotification($newAssignee, (int) $ticketId, 'notif_assigned');
            }

            $db->commit();

            foreach ($notifIds as $notifId) {
                Notification::sendEmail($notifId);
            }
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Ticket::assign failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether a user may modify a ticket (change its status / assignment),
     * following docs/BACKEND_GUIDE.md:
     *   - admin : any ticket;
     *   - agent : only tickets currently assigned to them (an unassigned ticket
     *             must be claimed first);
     *   - employee : never.
     *
     * @param array<string,mixed> $ticket A row from findDetailById.
     * @param int                 $userId
     * @param string              $role
     * @return bool
     */
    public static function canModify(array $ticket, $userId, $role)
    {
        switch ($role) {
            case 'admin':
                return true;
            case 'agent':
                return $ticket['assigned_to'] !== null
                    && (int) $ticket['assigned_to'] === (int) $userId;
            default:
                return false;
        }
    }

    /**
     * Whether a user may access a ticket (view its detail / download its
     * attachments), following the RBAC matrix in docs/SECURITY_AUTH.md:
     *   - admin    : every ticket;
     *   - agent    : tickets assigned to them, plus unassigned ones (which they
     *                may claim);
     *   - employee : only the tickets they created.
     *
     * @param array<string,mixed> $ticket A row from findDetailById.
     * @param int                 $userId
     * @param string              $role
     * @return bool
     */
    public static function canAccess(array $ticket, $userId, $role)
    {
        switch ($role) {
            case 'admin':
                return true;
            case 'agent':
                return $ticket['assigned_to'] === null
                    || (int) $ticket['assigned_to'] === (int) $userId;
            case 'employee':
                return (int) $ticket['created_by'] === (int) $userId;
            default:
                return false;
        }
    }

    // ---- Admin: reports & statistics (phase 9) -----------------------------

    /**
     * Ticket count per status, for every status (a status with no tickets still
     * appears with a count of 0) — drives the status-distribution chart and
     * the open/closed summary. Ordered by the canonical status id.
     *
     * @return array<int,array{code:string,cnt:int}>
     */
    public static function countByStatus()
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.code, COUNT(t.id) AS cnt
             FROM ticket_statuses s
             LEFT JOIN tickets t ON t.status_id = s.id
             GROUP BY s.id, s.code
             ORDER BY s.id'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Ticket count per category (every category, including those with no
     * tickets), for the category-distribution chart. Returns both name columns
     * so the view can pick the one matching the current language.
     *
     * @return array<int,array{name_ar:string,name_en:string,cnt:int}>
     */
    public static function countByCategory()
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.name_ar, c.name_en, COUNT(t.id) AS cnt
             FROM ticket_categories c
             LEFT JOIN tickets t ON t.category_id = c.id
             GROUP BY c.id, c.name_ar, c.name_en
             ORDER BY c.id'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Ticket count per assignee (busiest agents first), plus a single row for
     * the unassigned tickets (assignee_name = NULL). Feeds the "tickets per
     * agent" breakdown.
     *
     * @return array<int,array{assignee_name:?string,cnt:int}>
     */
    public static function countByAgent()
    {
        $stmt = Database::connection()->prepare(
            'SELECT au.full_name AS assignee_name, COUNT(*) AS cnt
             FROM tickets t
             LEFT JOIN users au ON au.id = t.assigned_to
             GROUP BY t.assigned_to, au.full_name
             ORDER BY cnt DESC, au.full_name ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * High-level totals for the summary cards: all tickets, the still-open ones
     * (status not in resolved/closed) and the closed ones.
     *
     * @return array{total:int,open:int,closed:int}
     */
    public static function statusTotals()
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN s.code NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN s.code = 'closed' THEN 1 ELSE 0 END) AS closed_count
             FROM tickets t
             JOIN ticket_statuses s ON s.id = t.status_id"
        );
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'total'  => (int) ($row['total'] ?? 0),
            'open'   => (int) ($row['open_count'] ?? 0),
            'closed' => (int) ($row['closed_count'] ?? 0),
        ];
    }

    /**
     * Average resolution time in hours over closed tickets (closed_at -
     * created_at), with the number of closed tickets it is based on. avg_hours
     * is null when nothing has been closed yet.
     *
     * @return array{avg_hours:?float,closed_count:int}
     */
    public static function avgResolution()
    {
        $stmt = Database::connection()->prepare(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) AS avg_hours,
                    COUNT(*) AS closed_count
             FROM tickets
             WHERE closed_at IS NOT NULL'
        );
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'avg_hours'    => $row['avg_hours'] !== null ? (float) $row['avg_hours'] : null,
            'closed_count' => (int) ($row['closed_count'] ?? 0),
        ];
    }

    /**
     * Overdue tickets: still-open (status not resolved/closed) and older than
     * their priority's SLA — the exact "Overdue" rule from
     * docs/BACKEND_GUIDE.md. Most-overdue first (largest breach of SLA).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function findOverdue()
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.ticket_number, t.title, t.created_at,
                    s.code  AS status_code,
                    p.level AS priority_level,
                    p.sla_hours,
                    c.name_ar AS category_name_ar,
                    c.name_en AS category_name_en,
                    au.full_name AS assignee_name,
                    TIMESTAMPDIFF(HOUR, t.created_at, NOW()) AS age_hours
             FROM tickets t
             JOIN ticket_statuses   s  ON s.id  = t.status_id
             JOIN ticket_priorities p  ON p.id  = t.priority_id
             JOIN ticket_categories c  ON c.id  = t.category_id
             LEFT JOIN users        au ON au.id = t.assigned_to
             WHERE s.code NOT IN ('resolved','closed')
               AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > p.sla_hours
             ORDER BY (TIMESTAMPDIFF(HOUR, t.created_at, NOW()) - p.sla_hours) DESC,
                      t.created_at ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Role-aware dashboard summary figures (phase rebrand). Each role gets the
     * four counters its landing dashboard shows, computed with prepared
     * statements over the live tables. Status codes and the "Overdue" rule are
     * the canonical ones from docs/DATABASE.md / docs/BACKEND_GUIDE.md
     * ("open" = status NOT IN ('resolved','closed')).
     *
     *   employee → total, open, in_progress, resolved (own tickets only)
     *   agent    → assigned_to_me, unassigned, in_progress (mine), overdue (mine)
     *   admin    → total, open, overdue, avg_resolution_hours
     *
     * @param int    $userId
     * @param string $role
     * @return array<string,int|float>
     */
    public static function dashboardStats($userId, $role)
    {
        $db = Database::connection();

        if ($role === 'employee') {
            $stmt = $db->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN s.code NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN s.code = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN s.code = 'resolved' THEN 1 ELSE 0 END) AS resolved
                 FROM tickets t
                 JOIN ticket_statuses s ON s.id = t.status_id
                 WHERE t.created_by = :uid"
            );
            $stmt->execute([':uid' => (int) $userId]);
            $row = $stmt->fetch() ?: [];

            return [
                'total'       => (int) ($row['total'] ?? 0),
                'open'        => (int) ($row['open_count'] ?? 0),
                'in_progress' => (int) ($row['in_progress'] ?? 0),
                'resolved'    => (int) ($row['resolved'] ?? 0),
            ];
        }

        if ($role === 'agent') {
            // EMULATE_PREPARES is off, so every placeholder must be distinct
            // even when it carries the same agent id.
            $stmt = $db->prepare(
                "SELECT
                    SUM(CASE WHEN t.assigned_to = :uid_a THEN 1 ELSE 0 END) AS assigned_to_me,
                    SUM(CASE WHEN t.assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned,
                    SUM(CASE WHEN t.assigned_to = :uid_b AND s.code = 'in_progress'
                             THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN t.assigned_to = :uid_c
                              AND s.code NOT IN ('resolved','closed')
                              AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > p.sla_hours
                             THEN 1 ELSE 0 END) AS overdue
                 FROM tickets t
                 JOIN ticket_statuses   s ON s.id = t.status_id
                 JOIN ticket_priorities p ON p.id = t.priority_id"
            );
            $stmt->execute([
                ':uid_a' => (int) $userId,
                ':uid_b' => (int) $userId,
                ':uid_c' => (int) $userId,
            ]);
            $row = $stmt->fetch() ?: [];

            return [
                'assigned_to_me' => (int) ($row['assigned_to_me'] ?? 0),
                'unassigned'     => (int) ($row['unassigned'] ?? 0),
                'in_progress'    => (int) ($row['in_progress'] ?? 0),
                'overdue'        => (int) ($row['overdue'] ?? 0),
            ];
        }

        // admin (default for the privileged role)
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN s.code NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN s.code NOT IN ('resolved','closed')
                          AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > p.sla_hours
                         THEN 1 ELSE 0 END) AS overdue,
                AVG(CASE WHEN t.closed_at IS NOT NULL
                         THEN TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at) END) AS avg_resolution_hours
             FROM tickets t
             JOIN ticket_statuses   s ON s.id = t.status_id
             JOIN ticket_priorities p ON p.id = t.priority_id"
        );
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'total'                => (int) ($row['total'] ?? 0),
            'open'                 => (int) ($row['open_count'] ?? 0),
            'overdue'              => (int) ($row['overdue'] ?? 0),
            'avg_resolution_hours' => $row['avg_resolution_hours'] !== null
                ? (int) round((float) $row['avg_resolution_hours'])
                : 0,
        ];
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Build the shared WHERE clause + bound params for the creator queries.
     *
     * @param int                                                     $userId
     * @param array{status_id?:int,category_id?:int,priority_id?:int} $filters
     * @return array{0:string,1:array<string,int>}
     */
    private static function buildFilter($userId, array $filters)
    {
        $clauses = ['t.created_by = :created_by'];
        $params  = [':created_by' => (int) $userId];

        if (!empty($filters['status_id'])) {
            $clauses[] = 't.status_id = :status_id';
            $params[':status_id'] = (int) $filters['status_id'];
        }
        if (!empty($filters['category_id'])) {
            $clauses[] = 't.category_id = :category_id';
            $params[':category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['priority_id'])) {
            $clauses[] = 't.priority_id = :priority_id';
            $params[':priority_id'] = (int) $filters['priority_id'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Build the shared WHERE clause + bound params for the agent queries:
     * tickets assigned to the agent plus all unassigned ones, with the optional
     * status/category/priority filters.
     *
     * @param int                                                     $agentId
     * @param array{status_id?:int,category_id?:int,priority_id?:int} $filters
     * @return array{0:string,1:array<string,int>}
     */
    private static function buildAgentFilter($agentId, array $filters)
    {
        $clauses = ['(t.assigned_to = :agent_id OR t.assigned_to IS NULL)'];
        $params  = [':agent_id' => (int) $agentId];

        if (!empty($filters['status_id'])) {
            $clauses[] = 't.status_id = :status_id';
            $params[':status_id'] = (int) $filters['status_id'];
        }
        if (!empty($filters['category_id'])) {
            $clauses[] = 't.category_id = :category_id';
            $params[':category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['priority_id'])) {
            $clauses[] = 't.priority_id = :priority_id';
            $params[':priority_id'] = (int) $filters['priority_id'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Build the shared WHERE clause + bound params for the admin all-tickets
     * queries: no ownership scope, with optional status/category/priority and
     * assignee filters (a specific agent id, or the unassigned set).
     *
     * @param array{status_id?:int,category_id?:int,priority_id?:int,assigned_to?:int,unassigned?:bool} $filters
     * @return array{0:string,1:array<string,int>}
     */
    private static function buildAllFilter(array $filters)
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['status_id'])) {
            $clauses[] = 't.status_id = :status_id';
            $params[':status_id'] = (int) $filters['status_id'];
        }
        if (!empty($filters['category_id'])) {
            $clauses[] = 't.category_id = :category_id';
            $params[':category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['priority_id'])) {
            $clauses[] = 't.priority_id = :priority_id';
            $params[':priority_id'] = (int) $filters['priority_id'];
        }
        if (!empty($filters['unassigned'])) {
            $clauses[] = 't.assigned_to IS NULL';
        } elseif (!empty($filters['assigned_to'])) {
            $clauses[] = 't.assigned_to = :assigned_to';
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$where, $params];
    }

    /**
     * Display name of a user by id (for ticket_history assignee values).
     *
     * @param int $userId
     * @return string|null
     */
    private static function userName($userId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT full_name FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $userId]);
        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
    }

    /**
     * Compute the next ticket_number for today: TCK-YYYYMMDD-XXXX, where XXXX
     * is a 4-digit daily sequence (highest existing + 1, starting at 0001).
     *
     * @return string
     */
    private static function nextTicketNumber()
    {
        $prefix = 'TCK-' . date('Ymd') . '-';

        $stmt = Database::connection()->prepare(
            'SELECT ticket_number FROM tickets
             WHERE ticket_number LIKE :prefix
             ORDER BY ticket_number DESC
             LIMIT 1'
        );
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        $seq = $last !== false ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve the id of the 'new' status (default for a freshly created ticket).
     *
     * @return int
     */
    private static function newStatusId()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM ticket_statuses WHERE code = :code LIMIT 1'
        );
        $stmt->execute([':code' => 'new']);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('Missing seed status: new');
        }

        return (int) $id;
    }

    /**
     * Insert a ticket_history row.
     *
     * @param int         $ticketId
     * @param int         $userId
     * @param string      $action  create / status_change / assign / comment
     * @param string|null $old
     * @param string|null $new
     * @return void
     */
    private static function addHistory($ticketId, $userId, $action, $old, $new)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO ticket_history
                (ticket_id, user_id, action, old_value, new_value, created_at)
             VALUES
                (:ticket_id, :user_id, :action, :old_value, :new_value, NOW())'
        );
        $stmt->execute([
            ':ticket_id' => (int) $ticketId,
            ':user_id'   => (int) $userId,
            ':action'    => $action,
            ':old_value' => $old,
            ':new_value' => $new,
        ]);
    }

    /**
     * Insert a notification row for a user about a ticket and return its id.
     * Delegates to the Notification model (single source for the table); the
     * row is written inside the caller's transaction, while the accompanying
     * e-mail is dispatched only after commit (see changeStatus / assign).
     *
     * @param int    $userId     recipient
     * @param int    $ticketId
     * @param string $messageKey translation key (e.g. notif_status_changed)
     * @return int The new notification id.
     */
    private static function addNotification($userId, $ticketId, $messageKey)
    {
        return Notification::create($userId, $ticketId, $messageKey);
    }
}
