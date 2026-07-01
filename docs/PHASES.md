# PHASES.md — خارطة تنفيذ المشروع (10 مراحل)

## المرحلة 1: الإعداد الأساسي والبنية
**الهدف:** إنشاء شجرة المجلدات كاملة + سكربت SQL + اتصال قاعدة البيانات + نقطة دخول أساسية.
**الملفات:** database/schema.sql, config/database.php, config/constants.php, core/Database.php, core/Router.php (مصفوفة توجيه فاضية), public/index.php، + إنشاء كل المجلدات من PROJECT_STRUCTURE.md (فاضية)
**معايير القبول:**
- تنفيذ schema.sql على MySQL ينشئ كل جداول DATABASE.md + بيانات Seed بدون أخطاء.
- Database.php يتصل بنجاح (Singleton، utf8mb4، ERRMODE_EXCEPTION).
- فتح public/index.php (وكذلك public/index.php?page=home) يعرض صفحة أولية بدون أخطاء PHP، ويُظهر أن آلية ?page= عبر Router.php تعمل.
- لا تُنشأ ملفات Models/Controllers/Views فعلية في هذه المرحلة (المجلدات فقط) — تُنشأ كل واحدة في مرحلتها.
**الحالة:** ✅

## المرحلة 2: المصادقة والصلاحيات (RBAC)
**الهدف:** نظام تسجيل دخول كامل مع LDAP (وضع mock)، جلسات، توجيه حسب الدور، ونظام ترجمة أساسي.
**الملفات:** config/ldap.php, core/Auth.php, core/Helpers.php (دالة t($key,$params) بالحد الأدنى), lang/ar.php, lang/en.php (مفاتيح المصادقة فقط), controllers/AuthController.php, views/auth/login.php, models/User.php
**معايير القبول:**
- تسجيل الدخول بمستخدم mock يعيد التوجيه حسب role (employee/agent/admin) إلى `?page=dashboard`.
- أنشئ صفحة `?page=dashboard` (ترحيب نصي بسيط حسب الدور فقط) — تُستبدل لاحقًا بلوحات المراحل 4/6/7. محاولة الوصول لها بدون جلسة → إعادة توجيه لصفحة الدخول.
- تطبيق RBAC Matrix من SECURITY_AUTH.md عبر Auth::require/requireAny.
- صفحة login.php وdashboard بلا أي نص ثابت — كل النصوص عبر t() من lang/ar.php وlang/en.php (حتى لو المفاتيح قليلة الآن).
**الحالة:** ✅

## المرحلة 3: نظام التصميم والتخطيط العام
**الهدف:** بناء Layout عام + design tokens + توسيع نظام الترجمة لتغطية كل الواجهة.
**الملفات:** public/assets/css/style.css, views/layout/*, lang/ar.php, lang/en.php (توسيع — إضافة باقي المفاتيح), core/Helpers.php (توسيع t() إن لزم)
**معايير القبول:**
- style.css يحتوي كل design tokens من FRONTEND_GUIDE.md.
- مبدّل اللغة (من المرحلة 2) يغيّر dir و lang ومحتوى النصوص فعليًا في كل الصفحات.
- Sidebar/Header/Footer تظهر بشكل صحيح RTL وLTR، وكل نصوصها عبر t().
**الحالة:** ✅

## المرحلة 4: فتح وعرض التذاكر (الموظف)
**الهدف:** نموذج فتح بلاغ جديد + صفحة "بلاغاتي".
**الملفات:** controllers/TicketController.php, models/Ticket.php, models/Attachment.php, views/employee/new_ticket.php, views/employee/my_tickets.php, views/components/badge_status.php, badge_priority.php, file_upload.php
**معايير القبول:**
- إنشاء تذكرة يولّد ticket_number بالصيغة الصحيحة ويُخزّن status=new، باتباع ترتيب "إنشاء تذكرة مع مرفق" في BACKEND_GUIDE.md (المرفق اختياري عند الإنشاء).
- "بلاغاتي" تعرض فقط تذاكر created_by = المستخدم الحالي مع badges للحالة والأولوية.
- التحقق من صحة المدخلات (عنوان، وصف، فئة، أولوية مطلوبة؛ المرفق اختياري ويُفحص حسب SECURITY_AUTH.md إن وُجد).
**الحالة:** ✅

## المرحلة 5: تفاصيل التذكرة والتعليقات والمرفقات
**الهدف:** صفحة تفاصيل تذكرة كاملة مع تعليقات ومرفقات.
**الملفات:** models/Comment.php, views/employee/ticket_view.php, views/components/file_upload.php (إعادة استخدام)، إضافة دالة download إلى controllers/TicketController.php + سطر في Router.php (?page=download)
**معايير القبول:**
- إضافة تعليق يُخزَّن ويظهر فورًا مع اسم المستخدم والتاريخ، ويُحدِّث tickets.updated_at.
- إضافة تعليق مع مرفق تستخدم نفس models/Attachment.php (من المرحلة 4) مع comment_id غير NULL، وتطبّق قواعد SECURITY_AUTH.md.
- المستخدم لا يستطيع فتح تذكرة لا تخصه (تحقق RBAC)، وروابط تحميل المرفقات تمر عبر `index.php?page=download&id={attachment_id}` مع فحص الصلاحية كما في SECURITY_AUTH.md.
**الحالة:** ✅

## المرحلة 6: لوحة الفني (Agent)
**الهدف:** لوحة الفني لعرض ومعالجة التذاكر.
**الملفات:** controllers/AgentController.php, views/agent/dashboard.php, views/agent/ticket_view.php
**معايير القبول:**
- الفني يرى التذاكر المعيّنة له + غير المعيّنة (لتعيينها لنفسه).
- تغيير الحالة أو التعيين يُسجَّل في ticket_history وينشئ صف notifications.
- فلترة القائمة حسب status/category/priority عبر $_GET.
**الحالة:** ✅

## المرحلة 7: لوحة الإدارة (Admin)
**الهدف:** إدارة المستخدمين، الفئات، الأولويات، وإعادة توزيع التذاكر.
**الملفات:** controllers/AdminController.php, views/admin/users.php, views/admin/categories.php
**معايير القبول:**
- admin يمكنه إضافة/تعديل/تعطيل مستخدم وتحديد role وdepartment.
- admin يمكنه إضافة/تعديل/تعطيل ticket_categories وticket_priorities.
- admin يمكنه إعادة تعيين أي تذكرة لأي فني (يُسجَّل في ticket_history).
**الحالة:** ✅

## المرحلة 8: نظام الإشعارات
**الهدف:** إشعارات داخل النظام + بريد إلكتروني عند تحديث حالة/تعيين/رد على تذكرة.
**الملفات:** models/Notification.php, core/Mailer.php (وضع mock SMTP), views/components/notifications_dropdown.php
**معايير القبول:**
- كل تغيير حالة/تعليق/تعيين ينشئ صف notifications (بـ message_key مناسب من lang/) للمستخدم المعني.
- جرس الإشعارات في الهيدر يعرض عدد غير المقروء، ويعرض كل إشعار عبر `t(message_key)` بلغة المستخدم الحالية، ويحدّث is_read عند الفتح.
- Mailer في وضع mock يكتب محتوى البريد في log بدل الإرسال الفعلي.
**الحالة:** ✅

## المرحلة 9: التقارير والإحصائيات
**الهدف:** لوحة إحصائيات للإدارة (عدد التذاكر حسب الحالة/الفئة/الفني، متوسط زمن الحل، المتأخرة).
**الملفات:** controllers/ReportController.php, views/admin/reports.php, public/assets/js/chart.min.js (مكتبة محلية، بدون CDN خارجي)
**معايير القبول:**
- عرض رسوم بيانية عبر public/assets/js/chart.min.js (محلية) لتوزيع التذاكر حسب الحالة والفئة.
- حساب متوسط زمن الحل (closed_at - created_at) وعرضه.
- قائمة التذاكر المتأخرة حسب sla_hours لكل أولوية.
**الحالة:** ✅

## المرحلة 10: المراجعة النهائية والتلميع
**الهدف:** مراجعة أمان شاملة + اكتمال الترجمة + تنظيف الكود.
**الملفات:** مراجعة لكل الملفات السابقة (بدون ملفات جديدة بالضرورة)
**معايير القبول:**
- كل صفحة محمية تتحقق من RBAC، وكل form يحتوي CSRF token صالح.
- لا نصوص ثابتة متبقية خارج lang/ar.php و lang/en.php.
- لا أخطاء PHP/استعلامات غير محضّرة (unprepared) في أي ملف.
**الحالة:** ✅

## المرحلة 11: دور مدير القسم (Department Manager)
**الهدف:** إضافة دور manager بين admin و agent مع نموذج إسناد هرمي: المدير العام يسند لقسم (عبر مدير القسم) أو لفني مباشرة ضمن قسم التذكرة؛ مدير القسم يعيد الإسناد لفنّيي قسمه ويعالج كفني؛ الفني لا يسند لنفسه.
**الملفات:** database/schema.sql (role ENUM + ticket_categories.department_id + seed) + database/migrations/2026_07_01_add_department_manager.sql, config/ldap.php, core/Auth.php (department_id في الجلسة), models/Ticket.php (canAccess/canModify + findForManager/countForManager + dashboardStats), models/User.php (نطاق القسم + إدارة الأقسام), controllers/ManagerController.php (+ views/manager/*), controllers/AdminController.php (admin_departments + قسم التصنيف) + views/admin/departments.php, controllers/AuthController.php + views/dashboard.php, controllers/AgentController.php + views/agent/ticket_view.php (إزالة السحب), controllers/TicketController.php (إسناد ضمن القسم), core/Router.php, views/layout/sidebar.php, lang/ar.php + lang/en.php.
**معايير القبول:**
- مدير القسم يرى فقط تذاكر تصنيفات قسمه، ويسندها لفنّيي قسمه فقط، ويستطيع تغيير الحالة/التعليق.
- الفني يرى فقط التذاكر المسندة إليه ولا يملك زر السحب (assign_me).
- المدير العام يعيد الإسناد لمدير القسم أو لفني ضمن قسم التذكرة (مشتق من التصنيف).
- كل الفحوص عبر Ticket::canAccess/canModify، وكل النصوص عبر lang/، وكل الاستعلامات محضّرة.
**الحالة:** ✅
