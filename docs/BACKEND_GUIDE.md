# BACKEND_GUIDE.md — دورك: Senior PHP Backend Developer

## البنية
- Controller: يستقبل الطلب، يتحقق من الصلاحية (Auth::require)، يستدعي Model، يمرر النتيجة لـ View.
- Model: كلاس لكل كيان (User, Ticket, Comment, Attachment, Notification) — دوال CRUD واستعلامات خاصة بالكيان فقط.
- Database.php: Singleton لاتصال PDO واحد، charset=utf8mb4، PDO::ERRMODE_EXCEPTION.

## قواعد الكود
- كل استعلام: `prepare()` + `execute([...])` — ممنوع تضمين المتغيرات داخل نص SQL.
- كل دالة Model تُرجع array أو null أو bool فقط — لا echo ولا HTML داخل Models.
- التحقق من المدخلات (validation) يتم في Controller قبل استدعاء Model.
- الأخطاء المتوقعة (مثال: تذكرة غير موجودة) → رسالة من lang/ + redirect، لا die()/var_dump في الكود النهائي.
- عمليات DB داخل try/catch، تسجيل الخطأ عبر error_log()، رسالة عامة فقط للمستخدم.

## منطق التذاكر
- ticket_number يُولَّد في Ticket Model بصيغة `TCK-YYYYMMDD-XXXX` (XXXX تسلسلي يومي). معالجة التزامن: عند فشل INSERT بسبب تكرار UNIQUE، إعادة حساب XXXX والمحاولة (حتى 3 مرات) ضمن نفس transaction.
- عند الإنشاء: status_id = حالة 'new'، created_at = NOW()، وإدخال صف ticket_history بـ action='create'.
- عند تغيير الحالة أو التعيين: إدخال صف في ticket_history + صف في notifications.
- tickets.updated_at يُحدَّث (NOW()) عند: تغيير status، تغيير assigned_to، أو إضافة ticket_comments جديد على التذكرة.
- tickets.closed_at يُضبط فقط عند الانتقال لـ status = 'closed' (لا 'resolved')، ويُصفّر (NULL) لو أُعيد فتحها لاحقًا.
- "متأخرة" (Overdue): status NOT IN ('resolved','closed') AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > priority.sla_hours.

## إنشاء تذكرة مع مرفق (الترتيب الإلزامي)
1. التحقق من المدخلات (عنوان، وصف، فئة، أولوية).
2. بدء transaction، INSERT في tickets، أخذ lastInsertId كـ ticket_id.
3. إن وُجد ملف مرفوع: إنشاء uploads/tickets/{ticket_id}/ ونقل الملف بالقواعد في SECURITY_AUTH.md، ثم INSERT في ticket_attachments (comment_id = NULL).
4. عند نجاح كل الخطوات: commit. عند فشل نقل الملف: rollback كامل (لا تُترك تذكرة بدون مرفقها المطلوب).

## RBAC (التنفيذ العملي)
- كل method في Controller يبدأ بـ `Auth::require('role')` أو `Auth::requireAny([...])`.
- employee: يرى تذاكره فقط (WHERE created_by = user_id).
- agent: يرى التذاكر المعيّنة له، ويمكنه رؤية غير المعيّنة لتعيينها لنفسه.
- admin: وصول كامل + صفحات الإدارة والتقارير.

## القوائم والفلترة
- كل قائمة (تذاكر/مستخدمين) تدعم pagination (LIMIT/OFFSET) وفلترة عبر $_GET (status, category, priority).

## التنسيق العام
- التواريخ تُعرض عبر `formatDate()` في core/Helpers.php (تنسيق موحد عربي/إنكليزي).
- أي استجابة AJAX (إن وُجدت) بصيغة JSON: `{"success": bool, "data|message": ...}`.
