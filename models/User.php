<?php
/**
 * User model — read access to the users table.
 *
 * All queries use PDO prepared statements (project rule). Authorisation data
 * (role, is_active) always comes from here, never from the LDAP layer.
 */
class User
{
    /**
     * Find an active user by their LDAP/employee id.
     *
     * @param string $employeeId
     * @return array<string,mixed>|null The user row, or null if not found/inactive.
     */
    public static function findActiveByEmployeeId($employeeId)
    {
        $sql = 'SELECT id, employee_id, full_name, email, role, department_id, is_active
                FROM users
                WHERE employee_id = :employee_id AND is_active = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':employee_id' => $employeeId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Find a user by primary key.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findById($id)
    {
        $sql = 'SELECT id, employee_id, full_name, email, role, department_id, is_active
                FROM users
                WHERE id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    // ---- Admin: listing & lookups (phase 7) --------------------------------

    /**
     * List users for the admin panel (active and inactive), joined with their
     * department name, newest first. Supports role / is_active filters and
     * LIMIT/OFFSET pagination (docs/BACKEND_GUIDE.md).
     *
     * @param array{role?:string,is_active?:int} $filters
     * @param int                                $limit
     * @param int                                $offset
     * @return array<int,array<string,mixed>>
     */
    public static function all(array $filters = [], $limit = 20, $offset = 0)
    {
        [$where, $params] = self::buildAdminFilter($filters);

        $sql = 'SELECT u.id, u.employee_id, u.full_name, u.email, u.role,
                       u.department_id, u.is_active, u.created_at,
                       d.name_ar AS department_name_ar,
                       d.name_en AS department_name_en
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id
                ' . $where . '
                ORDER BY u.id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count of users matching the same filters as all() (for pagination).
     *
     * @param array{role?:string,is_active?:int} $filters
     * @return int
     */
    public static function countAll(array $filters = [])
    {
        [$where, $params] = self::buildAdminFilter($filters);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM users u ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Active agents (role = 'agent'), for the admin ticket-reassignment list.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function agents()
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, full_name FROM users
             WHERE role = 'agent' AND is_active = 1
             ORDER BY full_name"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Active users that a ticket can be assigned to (technicians + department
     * managers), for the admin all-tickets assignee filter.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function assignees()
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, full_name, role FROM users
             WHERE role IN ('agent','manager') AND is_active = 1
             ORDER BY FIELD(role, 'manager', 'agent'), full_name"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Active technicians (role = 'agent') of a given department, for the manager
     * ticket-assignment list. Returns an empty set when $departmentId is null.
     *
     * @param int|null $departmentId
     * @return array<int,array<string,mixed>>
     */
    public static function techniciansByDepartment($departmentId)
    {
        if ($departmentId === null) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            "SELECT id, full_name FROM users
             WHERE role = 'agent' AND is_active = 1 AND department_id = :dept
             ORDER BY full_name"
        );
        $stmt->execute([':dept' => (int) $departmentId]);

        return $stmt->fetchAll();
    }

    /**
     * Active users an admin may assign a ticket to within a department's
     * structure: that department's managers and technicians (docs/SECURITY_AUTH.md).
     * When $departmentId is null (e.g. the ticket's category has no department),
     * falls back to every assignable user so the admin is never stuck.
     *
     * @param int|null $departmentId
     * @return array<int,array<string,mixed>>
     */
    public static function assignableForDepartment($departmentId)
    {
        if ($departmentId === null) {
            return self::assignees();
        }
        $stmt = Database::connection()->prepare(
            "SELECT id, full_name, role FROM users
             WHERE role IN ('agent','manager') AND is_active = 1 AND department_id = :dept
             ORDER BY FIELD(role, 'manager', 'agent'), full_name"
        );
        $stmt->execute([':dept' => (int) $departmentId]);

        return $stmt->fetchAll();
    }

    /**
     * Departments lookup, for the user-form department selector. There is no
     * standalone department management UI in V1 (docs/DATABASE.md scope note);
     * the admin only assigns an existing department to a user.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function departments()
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name_ar, name_en FROM departments ORDER BY id'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * A single department row by id, or null when not found (edit-form load).
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function departmentFindById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name_ar, name_en FROM departments WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Create a department.
     *
     * @param array{name_ar:string,name_en:string} $data
     * @return int|null The new department id, or null on failure.
     */
    public static function departmentCreate(array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO departments (name_ar, name_en) VALUES (:name_ar, :name_en)'
            );
            $stmt->execute([
                ':name_ar' => (string) $data['name_ar'],
                ':name_en' => (string) $data['name_en'],
            ]);

            return (int) Database::connection()->lastInsertId();
        } catch (Throwable $e) {
            error_log('User::departmentCreate failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a department's names.
     *
     * @param int                                   $id
     * @param array{name_ar:string,name_en:string}  $data
     * @return bool
     */
    public static function departmentUpdate($id, array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE departments SET name_ar = :name_ar, name_en = :name_en WHERE id = :id'
            );
            $stmt->execute([
                ':name_ar' => (string) $data['name_ar'],
                ':name_en' => (string) $data['name_en'],
                ':id'      => (int) $id,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('User::departmentUpdate failed: ' . $e->getMessage());
            return false;
        }
    }

    // ---- Admin: write operations (phase 7) ---------------------------------

    /**
     * Create a user. Uniqueness of employee_id / email is enforced by the DB
     * (and pre-checked in the controller); a duplicate surfaces here as a
     * caught PDOException and returns null.
     *
     * @param array{employee_id:string,full_name:string,email:string,role:string,department_id:?int,is_active:int} $data
     * @return int|null The new user id on success, or null on failure.
     */
    public static function create(array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO users
                    (employee_id, full_name, email, role, department_id, is_active, created_at)
                 VALUES
                    (:employee_id, :full_name, :email, :role, :department_id, :is_active, NOW())'
            );
            $stmt->execute([
                ':employee_id'   => (string) $data['employee_id'],
                ':full_name'     => (string) $data['full_name'],
                ':email'         => (string) $data['email'],
                ':role'          => (string) $data['role'],
                ':department_id' => $data['department_id'] !== null ? (int) $data['department_id'] : null,
                ':is_active'     => (int) $data['is_active'],
            ]);

            return (int) Database::connection()->lastInsertId();
        } catch (Throwable $e) {
            error_log('User::create failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update an existing user's editable fields (employee_id is the immutable
     * LDAP key and is never changed here).
     *
     * @param int $id
     * @param array{full_name:string,email:string,role:string,department_id:?int,is_active:int} $data
     * @return bool
     */
    public static function update($id, array $data)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE users SET
                    full_name     = :full_name,
                    email         = :email,
                    role          = :role,
                    department_id = :department_id,
                    is_active     = :is_active
                 WHERE id = :id'
            );
            $stmt->execute([
                ':full_name'     => (string) $data['full_name'],
                ':email'         => (string) $data['email'],
                ':role'          => (string) $data['role'],
                ':department_id' => $data['department_id'] !== null ? (int) $data['department_id'] : null,
                ':is_active'     => (int) $data['is_active'],
                ':id'            => (int) $id,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('User::update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable or disable a user (soft toggle of is_active).
     *
     * @param int  $id
     * @param bool $active
     * @return bool
     */
    public static function setActive($id, $active)
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE users SET is_active = :active WHERE id = :id'
            );
            $stmt->execute([':active' => $active ? 1 : 0, ':id' => (int) $id]);

            return true;
        } catch (Throwable $e) {
            error_log('User::setActive failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether an employee_id already belongs to a user (optionally excluding a
     * given id, for the edit case).
     *
     * @param string $employeeId
     * @param int    $exceptId
     * @return bool
     */
    public static function existsEmployeeId($employeeId, $exceptId = 0)
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM users WHERE employee_id = :employee_id AND id <> :except LIMIT 1'
        );
        $stmt->execute([':employee_id' => (string) $employeeId, ':except' => (int) $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Whether an email already belongs to a user (optionally excluding a given
     * id, for the edit case).
     *
     * @param string $email
     * @param int    $exceptId
     * @return bool
     */
    public static function existsEmail($email, $exceptId = 0)
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM users WHERE email = :email AND id <> :except LIMIT 1'
        );
        $stmt->execute([':email' => (string) $email, ':except' => (int) $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Build the shared WHERE clause + bound params for the admin user listing.
     *
     * @param array{role?:string,is_active?:int} $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildAdminFilter(array $filters)
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['role'])) {
            $clauses[] = 'u.role = :role';
            $params[':role'] = (string) $filters['role'];
        }
        if (isset($filters['is_active'])) {
            $clauses[] = 'u.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$where, $params];
    }
}
