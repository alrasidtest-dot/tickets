<?php
/**
 * Router — maps a ?page= key to a [Controller, method] pair.
 *
 * The $routes map is the single source of truth for routing. Each phase
 * appends its own entries here exclusively; no parallel routing mechanism.
 * Phase 1 ships an empty map; real routes are added from phase 2 onward.
 */
class Router
{
    /**
     * Static route table: 'page_key' => ['ControllerClass', 'method'].
     *
     * @var array<string, array{0:string,1:string}>
     */
    private static $routes = [
        // Phase 2 — authentication & dashboard.
        'login'     => ['AuthController', 'login'],
        'logout'    => ['AuthController', 'logout'],
        'dashboard' => ['AuthController', 'dashboard'],

        // Phase 4 — employee ticket creation & listing.
        'ticket_new' => ['TicketController', 'newTicket'],
        'ticket_my'  => ['TicketController', 'myTickets'],

        // Phase 5 — ticket detail (comments + attachments) & secured download.
        'ticket_view' => ['TicketController', 'viewTicket'],
        'download'    => ['TicketController', 'download'],

        // Phase 6 — agent dashboard & ticket processing.
        'agent_dashboard'   => ['AgentController', 'dashboard'],
        'agent_ticket_view' => ['AgentController', 'viewTicket'],

        // Phase 11 — department manager: department queue + ticket processing.
        'manager_dashboard'   => ['ManagerController', 'dashboard'],
        'manager_ticket_view' => ['ManagerController', 'viewTicket'],

        // Phase 7 — admin: user management & category/priority management.
        'admin_users'      => ['AdminController', 'users'],
        'admin_categories' => ['AdminController', 'categories'],
        // Phase 11 — admin: department management (needed to route categories).
        'admin_departments' => ['AdminController', 'departments'],
        // Admin: all-tickets overview (status/category/priority/agent filters).
        'admin_tickets'    => ['AdminController', 'tickets'],

        // Phase 8 — in-system notifications (bell dropdown + mark-as-read).
        'notif_open'     => ['NotificationController', 'open'],
        'notif_read_all' => ['NotificationController', 'readAll'],

        // Phase 9 — admin reports & statistics dashboard.
        'admin_reports'  => ['ReportController', 'index'],
    ];

    /**
     * Resolve a page key to its [Controller, method] pair, or null if unknown.
     *
     * @param string $pageKey
     * @return array{0:string,1:string}|null
     */
    public static function resolve($pageKey)
    {
        return self::$routes[$pageKey] ?? null;
    }

    /**
     * Whether a page key is registered in the route table.
     *
     * @param string $pageKey
     * @return bool
     */
    public static function has($pageKey)
    {
        return isset(self::$routes[$pageKey]);
    }
}
