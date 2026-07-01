# PROJECT_STRUCTURE.md — شجرة المشروع الكاملة

```
it-helpdesk/
├── config/
│   ├── database.php       # إعدادات + اتصال PDO
│   ├── ldap.php           # إعدادات LDAP + وضع mock
│   └── constants.php       # ثوابت عامة (مسارات، روابط، حدود الرفع)
├── core/
│   ├── Database.php        # Singleton اتصال PDO
│   ├── Auth.php             # تسجيل دخول/خروج، جلسات، RBAC guards
│   ├── Router.php           # توجيه الطلبات للـ controllers
│   └── Helpers.php          # دوال مساعدة (تنسيق تاريخ، توليد رقم تذكرة)
├── models/
│   ├── User.php
│   ├── Ticket.php
│   ├── Comment.php
│   ├── Attachment.php
│   └── Notification.php
├── controllers/
│   ├── AuthController.php
│   ├── TicketController.php   # إنشاء/عرض تذاكر الموظف
│   ├── AgentController.php    # لوحة الفني
│   ├── ManagerController.php  # لوحة مدير القسم (تذاكر قسمه + الإسناد لفنّيي قسمه)
│   ├── AdminController.php    # لوحة الإدارة (تشمل إدارة الأقسام)
│   └── ReportController.php   # التقارير والإحصائيات
├── views/
│   ├── layout/
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   └── footer.php
│   ├── components/         # badge_status, badge_priority, pagination, alert, file_upload
│   ├── auth/login.php
│   ├── employee/           # new_ticket.php, my_tickets.php, ticket_view.php
│   ├── agent/               # dashboard.php, ticket_view.php
│   ├── manager/             # dashboard.php, ticket_view.php (مدير القسم)
│   └── admin/                # users.php, departments.php, categories.php, reports.php
├── lang/
│   ├── ar.php
│   └── en.php
├── uploads/
│   └── tickets/             # مرفقات لكل تذكرة، خارج public/، تُعرض عبر index.php?page=download
├── database/
│   ├── schema.sql            # سكربت إنشاء الجداول + seed
│   ├── seed_demo.php         # بيانات عرض تجريبية (CLI)
│   └── migrations/           # سكربتات ALTER لقواعد بيانات قائمة (مثل إضافة دور مدير القسم)
├── public/
│   ├── index.php             # نقطة الدخول الوحيدة + التوجيه (?page=)
│   └── assets/
│       ├── css/style.css     # design tokens + الأنماط
│       └── js/app.js
├── docs/                      # ملفات التوجيه (هذه الملفات)
└── CLAUDE.md
```
