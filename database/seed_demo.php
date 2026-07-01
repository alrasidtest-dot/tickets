<?php
/**
 * seed_demo.php — one-off demo data seeder (CLI only).
 *
 *   Usage:  php database/seed_demo.php
 *
 * Wipes all transactional/demo data and inserts a realistic data set with
 * Sudanese names so the system can be demonstrated professionally.
 *
 * IMPORTANT:
 *   - Does NOT touch the schema (no CREATE/ALTER/DROP). Only DELETE + INSERT.
 *   - The 'admin001' user is kept and updated in place; all other users are
 *     removed and re-inserted.
 *   - Lookup ids (statuses / priorities / categories) are read from the live
 *     database rather than assumed.
 *   - Every statement uses PDO prepared statements.
 *
 * WARNING — LOCAL DEVELOPMENT / DEMO ONLY.
 */

// ---------------------------------------------------------------------------
// CLI guard + bootstrap
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

define('CONFIG_PATH', dirname(__DIR__) . '/config');
require dirname(__DIR__) . '/core/Database.php';

$db = Database::connection();
$db->exec("SET NAMES utf8mb4");

// Running totals for the closing summary.
$counts = [
    'users'              => 0,
    'tickets'            => 0,
    'ticket_comments'    => 0,
    'ticket_history'     => 0,
    'notifications'      => 0,
];

// ---------------------------------------------------------------------------
// Small datetime helpers (system "today" = base reference)
// ---------------------------------------------------------------------------

/** Datetime string for N days ago at HH:MM. */
function daysAgo($days, $hour = 9, $min = 0)
{
    $d = new DateTime('today');
    $d->modify("-{$days} days");
    $d->setTime($hour, $min);
    return $d->format('Y-m-d H:i:s');
}

/** Add hours to a 'Y-m-d H:i:s' base and return the formatted result. */
function plusHours($base, $hours)
{
    $d = new DateTime($base);
    $d->modify(($hours >= 0 ? '+' : '-') . abs($hours) . ' hours');
    return $d->format('Y-m-d H:i:s');
}

// ===========================================================================
// 1) Clean existing data (FK-safe order)
// ===========================================================================
$db->exec('DELETE FROM notifications');
$db->exec('DELETE FROM ticket_history');
$db->exec('DELETE FROM ticket_attachments');
$db->exec('DELETE FROM ticket_comments');
$db->exec('DELETE FROM tickets');
$db->exec("DELETE FROM users WHERE employee_id <> 'admin001'");

// ===========================================================================
// 2) Resolve lookup ids from the live DB (do not assume)
// ===========================================================================

/** Build [code/level/name => id] map from a lookup table. */
function lookupMap(PDO $db, $sql)
{
    $map = [];
    foreach ($db->query($sql) as $row) {
        $map[(string) $row['k']] = (int) $row['id'];
    }
    return $map;
}

$statusId   = lookupMap($db, 'SELECT id, code AS k FROM ticket_statuses');
$priorityId = lookupMap($db, 'SELECT id, level AS k FROM ticket_priorities');
$categoryId = lookupMap($db, 'SELECT id, name_en AS k FROM ticket_categories');

// Default department (first available) for all demo users.
$deptId = (int) $db->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetchColumn();

// Sanity checks — fail loudly if the reference data is missing.
foreach (['new', 'in_progress', 'on_hold', 'resolved', 'closed'] as $code) {
    if (!isset($statusId[$code])) {
        exit("Missing ticket_statuses row for code '{$code}'. Run schema.sql first.\n");
    }
}
foreach ([1, 2, 3] as $lvl) {
    if (!isset($priorityId[$lvl])) {
        exit("Missing ticket_priorities row for level {$lvl}. Run schema.sql first.\n");
    }
}
foreach (['Hardware', 'Software', 'Network', 'Email', 'Other'] as $cat) {
    if (!isset($categoryId[$cat])) {
        exit("Missing ticket_categories row for '{$cat}'. Run schema.sql first.\n");
    }
}

// ===========================================================================
// 3) Users — update admin in place, insert the rest
// ===========================================================================
$adminCreatedAt = daysAgo(45, 8, 0);

// Update the existing admin001 row.
$updAdmin = $db->prepare(
    'UPDATE users
        SET full_name = :full_name, email = :email, role = :role,
            department_id = :dept, is_active = 1
      WHERE employee_id = :eid'
);
$updAdmin->execute([
    ':full_name' => 'عمر عبدالله محمد',
    ':email'     => 'omar.admin@bank-sudan.sd',
    ':role'      => 'admin',
    ':dept'      => $deptId,
    ':eid'       => 'admin001',
]);

// Insert agents + employees.
$insUser = $db->prepare(
    'INSERT INTO users (employee_id, full_name, email, role, department_id, is_active, created_at)
     VALUES (:eid, :full_name, :email, :role, :dept, 1, :created_at)'
);

$demoUsers = [
    // department managers (route + process tickets for their department)
    ['manager001', 'طارق عبدالماجد حسن', 'tariq.manager@bank-sudan.sd',    'manager',  42],
    ['manager002', 'هالة الصادق أحمد',   'hala.manager@bank-sudan.sd',     'manager',  42],
    // agents
    ['agent001', 'خالد إبراهيم يوسف',  'khalid.ibrahim@bank-sudan.sd',     'agent',    40],
    ['agent002', 'سارة أحمد عوض',       'sara.ahmed@bank-sudan.sd',         'agent',    40],
    ['agent003', 'مصطفى محمد علي',      'mustafa.mohammed@bank-sudan.sd',   'agent',    38],
    // employees
    ['emp001',   'فاطمة عبدالرحمن',     'fatima.abdulrahman@bank-sudan.sd', 'employee', 35],
    ['emp002',   'أحمد حسن خليل',        'ahmed.hassan@bank-sudan.sd',       'employee', 35],
    ['emp003',   'مريم يوسف إدريس',     'mariam.yousif@bank-sudan.sd',      'employee', 33],
    ['emp004',   'إبراهيم عثمان موسى',   'ibrahim.othman@bank-sudan.sd',     'employee', 30],
    ['emp005',   'نور الدين عبدالله',    'noureldeen.abdullah@bank-sudan.sd','employee', 28],
    ['emp006',   'زينب محمد طاهر',       'zeinab.mohammed@bank-sudan.sd',    'employee', 25],
];

foreach ($demoUsers as $u) {
    $insUser->execute([
        ':eid'        => $u[0],
        ':full_name'  => $u[1],
        ':email'      => $u[2],
        ':role'       => $u[3],
        ':dept'       => $deptId,
        ':created_at' => daysAgo($u[4], 8, 30),
    ]);
    $counts['users']++;
}

// Map employee_id -> user id (including admin) for FK references below.
$userId = [];
foreach ($db->query('SELECT id, employee_id, full_name FROM users') as $row) {
    $userId[$row['employee_id']] = (int) $row['id'];
}

/** Full name of a user by employee_id (used for assign history values). */
$userName = [];
foreach ($db->query('SELECT employee_id, full_name FROM users') as $row) {
    $userName[$row['employee_id']] = (string) $row['full_name'];
}

$adminId = $userId['admin001'];

// ===========================================================================
// 4-7) Tickets + comments + history + notifications
// ===========================================================================

// Prepared statements reused inside the loop.
$insTicket = $db->prepare(
    'INSERT INTO tickets
        (ticket_number, title, description, category_id, priority_id, status_id,
         created_by, assigned_to, created_at, updated_at, closed_at)
     VALUES
        (:num, :title, :descr, :cat, :prio, :status,
         :created_by, :assigned_to, :created_at, :updated_at, :closed_at)'
);
$insComment = $db->prepare(
    'INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at)
     VALUES (:ticket_id, :user_id, :comment, :created_at)'
);
$insHistory = $db->prepare(
    'INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value, created_at)
     VALUES (:ticket_id, :user_id, :action, :old, :new, :created_at)'
);
$insNotif = $db->prepare(
    'INSERT INTO notifications (user_id, ticket_id, message_key, is_read, email_sent, created_at)
     VALUES (:user_id, :ticket_id, :message_key, :is_read, :email_sent, :created_at)'
);

/*
 * Demo ticket definitions.
 * Fields:
 *   title, descr, cat (name_en), prio (level 1/2/3), status (code),
 *   by (creator employee_id), to (assignee employee_id or null),
 *   days (days ago created), hour (creation hour).
 */
$tickets = [
    // ---- closed (oldest) ------------------------------------------------
    [
        'title' => 'فشل النسخ الاحتياطي اليومي للبيانات',
        'descr' => 'لم تكتمل عملية النسخ الاحتياطي المجدولة لقاعدة بيانات العمليات الليلة الماضية وظهرت رسالة فشل في سجل المهام.',
        'cat' => 'Other', 'prio' => 1, 'status' => 'closed',
        'by' => 'emp004', 'to' => 'agent002', 'days' => 14, 'hour' => 8,
    ],
    [
        'title' => 'مشكلة في تحديث بيانات العميل بالنظام',
        'descr' => 'عند محاولة تعديل عنوان أحد العملاء يظهر خطأ يمنع حفظ التغييرات في نظام إدارة العملاء.',
        'cat' => 'Software', 'prio' => 2, 'status' => 'closed',
        'by' => 'emp002', 'to' => 'agent001', 'days' => 13, 'hour' => 10,
    ],
    [
        'title' => 'كاميرا المراقبة في الردهة الرئيسية لا تعمل',
        'descr' => 'كاميرا المراقبة المثبّتة في الردهة الرئيسية لا تعرض أي صورة على شاشة غرفة الأمن منذ صباح اليوم.',
        'cat' => 'Hardware', 'prio' => 2, 'status' => 'closed',
        'by' => 'emp006', 'to' => 'agent003', 'days' => 12, 'hour' => 9,
    ],

    // ---- resolved -------------------------------------------------------
    [
        'title' => 'طابعة كشف الحساب معطّلة في فرع أم درمان',
        'descr' => 'طابعة كشوف الحساب في فرع أم درمان لا تستجيب لأوامر الطباعة وتظهر إشارة خطأ حمراء.',
        'cat' => 'Hardware', 'prio' => 2, 'status' => 'resolved',
        'by' => 'emp001', 'to' => 'agent002', 'days' => 11, 'hour' => 11,
    ],
    [
        'title' => 'فشل طباعة تقارير نهاية اليوم',
        'descr' => 'يتعذّر إصدار تقارير نهاية اليوم المالية حيث تتوقف العملية عند نسبة 80% دون إكمال.',
        'cat' => 'Software', 'prio' => 2, 'status' => 'resolved',
        'by' => 'emp003', 'to' => 'agent003', 'days' => 10, 'hour' => 14,
    ],
    [
        'title' => 'جهاز الحضور والانصراف لا يقرأ البصمة',
        'descr' => 'جهاز البصمة عند المدخل لا يتعرّف على بصمات الموظفين ويطلب إعادة المحاولة باستمرار.',
        'cat' => 'Hardware', 'prio' => 3, 'status' => 'resolved',
        'by' => 'emp005', 'to' => 'agent001', 'days' => 9, 'hour' => 8,
    ],
    [
        'title' => 'عدم تزامن ساعة الجهاز مع خادم الوقت',
        'descr' => 'أحد أجهزة الموظفين يعرض وقتًا غير صحيح ولا يتزامن مع خادم الوقت المركزي مما يؤثر على ختم العمليات.',
        'cat' => 'Other', 'prio' => 3, 'status' => 'resolved',
        'by' => 'emp002', 'to' => 'agent002', 'days' => 8, 'hour' => 13,
    ],

    // ---- on_hold --------------------------------------------------------
    [
        'title' => 'نظام الرواتب لا يُحدّث الأرصدة',
        'descr' => 'بعد تشغيل دورة الرواتب الشهرية لم تنعكس المبالغ على أرصدة حسابات الموظفين في النظام.',
        'cat' => 'Software', 'prio' => 1, 'status' => 'on_hold',
        'by' => 'emp001', 'to' => 'agent002', 'days' => 8, 'hour' => 9,
    ],
    [
        'title' => 'تعارض في إعدادات IP لأجهزة الفرع الجديد',
        'descr' => 'تظهر رسالة تعارض في عناوين IP عند تشغيل أجهزة الفرع الجديد مما يمنع اتصالها بالشبكة الداخلية.',
        'cat' => 'Network', 'prio' => 2, 'status' => 'on_hold',
        'by' => 'emp004', 'to' => 'agent003', 'days' => 7, 'hour' => 10,
    ],
    [
        'title' => 'عدم القدرة على الوصول لنظام أرشفة العقود',
        'descr' => 'لا يمكن فتح نظام أرشفة العقود حيث تظهر رسالة انتهاء صلاحية الجلسة فور تسجيل الدخول.',
        'cat' => 'Software', 'prio' => 3, 'status' => 'on_hold',
        'by' => 'emp003', 'to' => 'agent001', 'days' => 6, 'hour' => 12,
    ],

    // ---- in_progress ----------------------------------------------------
    [
        'title' => 'تعذّر الدخول لنظام الحوالات الداخلية',
        'descr' => 'يتعذّر على موظفي قسم الحوالات تسجيل الدخول لنظام الحوالات الداخلية وتظهر رسالة بيانات اعتماد غير صحيحة رغم صحتها.',
        'cat' => 'Software', 'prio' => 1, 'status' => 'in_progress',
        'by' => 'emp002', 'to' => 'agent001', 'days' => 5, 'hour' => 9,
    ],
    [
        'title' => 'بطء شديد في شبكة الإنترنت بالطابق الثاني',
        'descr' => 'يعاني موظفو الطابق الثاني من بطء شديد في تصفّح الأنظمة الداخلية منذ صباح اليوم.',
        'cat' => 'Network', 'prio' => 2, 'status' => 'in_progress',
        'by' => 'emp005', 'to' => 'agent002', 'days' => 4, 'hour' => 10,
    ],
    [
        'title' => 'عدم استجابة شاشة نقطة البيع',
        'descr' => 'شاشة نقطة البيع في الكاونتر الأمامي لا تستجيب للمس وتتطلب إعادة تشغيل متكرر.',
        'cat' => 'Hardware', 'prio' => 2, 'status' => 'in_progress',
        'by' => 'emp006', 'to' => 'agent003', 'days' => 3, 'hour' => 11,
    ],
    [
        'title' => 'خطأ عند تسجيل الدخول لبريد المصرف',
        'descr' => 'يظهر خطأ في المصادقة عند محاولة الدخول إلى بريد المصرف الإلكتروني من بعض الأجهزة.',
        'cat' => 'Email', 'prio' => 2, 'status' => 'in_progress',
        'by' => 'emp003', 'to' => 'agent001', 'days' => 3, 'hour' => 13,
    ],

    // ---- new (unassigned, newest) ---------------------------------------
    [
        'title' => 'جهاز الصراف الآلي لا يقبل البطاقات',
        'descr' => 'جهاز الصراف الآلي أمام الفرع الرئيسي يرفض جميع البطاقات ويعيدها دون إتمام أي عملية.',
        'cat' => 'Hardware', 'prio' => 1, 'status' => 'new',
        'by' => 'emp001', 'to' => null, 'days' => 3, 'hour' => 15,
    ],
    [
        'title' => 'انقطاع مفاجئ في خدمة الإنترنت البنكي',
        'descr' => 'انقطعت خدمة الإنترنت البنكي عن العملاء بشكل مفاجئ وتظهر رسالة تعذّر الاتصال بالخادم.',
        'cat' => 'Network', 'prio' => 1, 'status' => 'new',
        'by' => 'emp004', 'to' => null, 'days' => 2, 'hour' => 9,
    ],
    [
        'title' => 'شاشة لوحة التحكم الرئيسية تُظهر خطأ 503',
        'descr' => 'لوحة التحكم الإدارية الرئيسية تعرض الخطأ 503 (الخدمة غير متاحة) عند محاولة فتحها.',
        'cat' => 'Software', 'prio' => 1, 'status' => 'new',
        'by' => 'emp002', 'to' => null, 'days' => 1, 'hour' => 10,
    ],
    [
        'title' => 'رفض طلب حوالة دولية بدون سبب واضح',
        'descr' => 'يرفض النظام معالجة طلب حوالة دولية ويُظهر رسالة خطأ عامة دون توضيح السبب.',
        'cat' => 'Software', 'prio' => 2, 'status' => 'new',
        'by' => 'emp006', 'to' => null, 'days' => 0, 'hour' => 9,
    ],
];

// Comment text variations (kept realistic + professional).
$agentOpen = [
    'تم استلام البلاغ وبدء التحقيق في المشكلة، سنوافيكم بالمستجدات.',
    'شكرًا لإبلاغنا، تم تسجيل الحالة وجارٍ فحص السبب الآن.',
    'استلمنا طلبكم وبدأنا بمعاينة الجهاز عن بُعد.',
];
$empReply = [
    'شكرًا لكم، في انتظار الحل.',
    'تمام، نشكر سرعة استجابتكم.',
    'حسنًا، نأمل حلّ المشكلة في أقرب وقت.',
];
$agentClose = [
    'تمت معالجة المشكلة وإغلاق البلاغ بنجاح.',
    'تم حل المشكلة والتأكد من عمل الخدمة بشكل طبيعي.',
    'تمّ إصلاح العطل والتحقق من الحل مع المستخدم.',
];

$i = 0;
foreach ($tickets as $t) {
    $i++;

    $createdBy  = $userId[$t['by']];
    $assignedTo = $t['to'] !== null ? $userId[$t['to']] : null;
    $createdAt  = daysAgo($t['days'], $t['hour']);

    // Build the event timeline depending on the final status.
    $assignAt   = plusHours($createdAt, 2);
    $startAt    = plusHours($createdAt, 3);   // new -> in_progress
    $holdAt     = plusHours($createdAt, 26);  // in_progress -> on_hold
    $resolveAt  = plusHours($createdAt, 20);  // in_progress -> resolved
    $closeAt    = plusHours($createdAt, 44);  // resolved -> closed

    $updatedAt = $createdAt;
    $closedAt  = null;
    switch ($t['status']) {
        case 'in_progress': $updatedAt = $startAt;   break;
        case 'on_hold':     $updatedAt = $holdAt;     break;
        case 'resolved':    $updatedAt = $resolveAt;  break;
        case 'closed':      $updatedAt = $closeAt; $closedAt = $closeAt; break;
    }

    $ticketNumber = sprintf('TCK-%s-%04d', (new DateTime($createdAt))->format('Ymd'), $i);

    $insTicket->execute([
        ':num'         => $ticketNumber,
        ':title'       => $t['title'],
        ':descr'       => $t['descr'],
        ':cat'         => $categoryId[$t['cat']],
        ':prio'        => $priorityId[$t['prio']],
        ':status'      => $statusId[$t['status']],
        ':created_by'  => $createdBy,
        ':assigned_to' => $assignedTo,
        ':created_at'  => $createdAt,
        ':updated_at'  => $updatedAt,
        ':closed_at'   => $closedAt,
    ]);
    $ticketId = (int) $db->lastInsertId();
    $counts['tickets']++;

    // --- History: every ticket starts with a 'create' row. ---------------
    $insHistory->execute([
        ':ticket_id' => $ticketId, ':user_id' => $createdBy,
        ':action' => 'create', ':old' => null, ':new' => null,
        ':created_at' => $createdAt,
    ]);
    $counts['ticket_history']++;

    $cidx = ($i - 1) % 3; // rotate comment variations

    if ($t['status'] !== 'new') {
        // Assigned by the admin (dispatcher) -> assign history row.
        $insHistory->execute([
            ':ticket_id' => $ticketId, ':user_id' => $adminId,
            ':action' => 'assign', ':old' => null,
            ':new' => $userName[$t['to']], ':created_at' => $assignAt,
        ]);
        $counts['ticket_history']++;

        // new -> in_progress (performed by the assigned agent).
        $insHistory->execute([
            ':ticket_id' => $ticketId, ':user_id' => $assignedTo,
            ':action' => 'status_change', ':old' => 'new', ':new' => 'in_progress',
            ':created_at' => $startAt,
        ]);
        $counts['ticket_history']++;

        // Opening comment from the agent + employee reply.
        $insComment->execute([
            ':ticket_id' => $ticketId, ':user_id' => $assignedTo,
            ':comment' => $agentOpen[$cidx], ':created_at' => $startAt,
        ]);
        $counts['ticket_comments']++;
        $insComment->execute([
            ':ticket_id' => $ticketId, ':user_id' => $createdBy,
            ':comment' => $empReply[$cidx], ':created_at' => plusHours($startAt, 2),
        ]);
        $counts['ticket_comments']++;

        // Status-specific tail of the timeline.
        if ($t['status'] === 'on_hold') {
            $insHistory->execute([
                ':ticket_id' => $ticketId, ':user_id' => $assignedTo,
                ':action' => 'status_change', ':old' => 'in_progress', ':new' => 'on_hold',
                ':created_at' => $holdAt,
            ]);
            $counts['ticket_history']++;
        }

        if ($t['status'] === 'resolved' || $t['status'] === 'closed') {
            $insHistory->execute([
                ':ticket_id' => $ticketId, ':user_id' => $assignedTo,
                ':action' => 'status_change', ':old' => 'in_progress', ':new' => 'resolved',
                ':created_at' => $resolveAt,
            ]);
            $counts['ticket_history']++;

            // Closing comment from the agent.
            $insComment->execute([
                ':ticket_id' => $ticketId, ':user_id' => $assignedTo,
                ':comment' => $agentClose[$cidx], ':created_at' => $resolveAt,
            ]);
            $counts['ticket_comments']++;
        }

        if ($t['status'] === 'closed') {
            $insHistory->execute([
                ':ticket_id' => $ticketId, ':user_id' => $assignedTo,
                ':action' => 'status_change', ':old' => 'resolved', ':new' => 'closed',
                ':created_at' => $closeAt,
            ]);
            $counts['ticket_history']++;
        }
    }

    // --- Notifications ----------------------------------------------------
    // Only the three keys defined in lang/*.php are used so they render via t().
    if ($t['status'] === 'new') {
        // Unassigned ticket sitting in the queue: alert all agents (unread).
        foreach (['agent001', 'agent002', 'agent003'] as $eid) {
            $insNotif->execute([
                ':user_id' => $userId[$eid], ':ticket_id' => $ticketId,
                ':message_key' => 'notif_status_changed', ':is_read' => 0,
                ':email_sent' => 0, ':created_at' => $createdAt,
            ]);
            $counts['notifications']++;
        }
    } else {
        // Notify the assigned agent that they received the ticket.
        $insNotif->execute([
            ':user_id' => $assignedTo, ':ticket_id' => $ticketId,
            ':message_key' => 'notif_assigned',
            ':is_read' => ($t['status'] === 'in_progress' || $t['status'] === 'on_hold') ? 0 : 1,
            ':email_sent' => 1, ':created_at' => $assignAt,
        ]);
        $counts['notifications']++;

        // Notify the ticket creator about the update on their ticket.
        $creatorKey  = ($t['status'] === 'in_progress') ? 'notif_assigned' : 'notif_status_changed';
        // Recent/active tickets stay unread; resolved & closed are already seen.
        $creatorRead = ($t['status'] === 'resolved' || $t['status'] === 'closed') ? 1 : 0;
        $insNotif->execute([
            ':user_id' => $createdBy, ':ticket_id' => $ticketId,
            ':message_key' => $creatorKey, ':is_read' => $creatorRead,
            ':email_sent' => 1, ':created_at' => $updatedAt,
        ]);
        $counts['notifications']++;
    }
}

// ===========================================================================
// Summary
// ===========================================================================
echo "Demo data seeded successfully.\n";
echo "-----------------------------------------\n";
printf("  users (excl. admin update) : %d\n", $counts['users']);
printf("  tickets                    : %d\n", $counts['tickets']);
printf("  ticket_comments            : %d\n", $counts['ticket_comments']);
printf("  ticket_history             : %d\n", $counts['ticket_history']);
printf("  notifications              : %d\n", $counts['notifications']);
echo "-----------------------------------------\n";
echo "admin001 updated in place (kept). Login password unchanged (admin123).\n";
echo "Demo users password (mock LDAP): Sudan@2025\n";
