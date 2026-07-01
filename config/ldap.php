<?php
/**
 * LDAP settings + authentication mode.
 * Consumed by controllers/AuthController.php during login.
 *
 * mode:
 *   'live' — bind against a real directory using host/port/base_dn.
 *   'mock' — authenticate against the static mock_users array below
 *            (development only; no real network call).
 *
 * NOTE: the first entry of mock_users MUST be employee_id = 'admin001'
 * to match the initial admin user seeded in database/schema.sql
 * (see docs/SECURITY_AUTH.md and docs/DATABASE.md Seed).
 *
 * WARNING — LOCAL DEVELOPMENT ONLY.
 * The mock passwords below are placeholders for local testing and must
 * never reach production. In 'live' mode no passwords are stored here.
 */

return [
    // LDAP_MODE
    'mode'    => 'mock',

    // Connection settings (used only in 'live' mode).
    'host'    => 'ldap://dc.bank.local',
    'port'    => 389,
    'base_dn' => 'OU=Users,DC=bank,DC=local',

    /*
     * Static directory used in 'mock' mode. Keyed by employee_id; each entry
     * simulates the attributes an LDAP bind would return. Authentication in
     * mock mode succeeds when employee_id exists here AND the password matches.
     * Authorisation (role) is always read from the users table, never here.
     */
    'mock_users' => [
        // First entry MUST stay 'admin001' (see note above).
        'admin001' => ['employee_id' => 'admin001', 'password' => 'admin123'],

        // Department managers (route + process tickets for their department).
        'manager001' => ['employee_id' => 'manager001', 'password' => 'Sudan@2025'],
        'manager002' => ['employee_id' => 'manager002', 'password' => 'Sudan@2025'],

        // Demo directory — passwords match database/seed_demo.php (local dev only).
        'agent001' => ['employee_id' => 'agent001', 'password' => 'Sudan@2025'],
        'agent002' => ['employee_id' => 'agent002', 'password' => 'Sudan@2025'],
        'agent003' => ['employee_id' => 'agent003', 'password' => 'Sudan@2025'],
        'emp001'   => ['employee_id' => 'emp001',   'password' => 'Sudan@2025'],
        'emp002'   => ['employee_id' => 'emp002',   'password' => 'Sudan@2025'],
        'emp003'   => ['employee_id' => 'emp003',   'password' => 'Sudan@2025'],
        'emp004'   => ['employee_id' => 'emp004',   'password' => 'Sudan@2025'],
        'emp005'   => ['employee_id' => 'emp005',   'password' => 'Sudan@2025'],
        'emp006'   => ['employee_id' => 'emp006',   'password' => 'Sudan@2025'],
    ],
];
