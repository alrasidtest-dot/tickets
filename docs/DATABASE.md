# DATABASE.md — مرجع مخطط قاعدة البيانات (مرجع قراءة فقط)

## users
| الحقل | النوع | ملاحظات |
|---|---|---|
| id | INT PK AI | |
| employee_id | VARCHAR(50) UNIQUE | معرّف LDAP/الموظف |
| full_name | VARCHAR(100) | |
| email | VARCHAR(100) UNIQUE | |
| role | ENUM('employee','agent','manager','admin') | manager = مدير قسم |
| department_id | INT FK→departments.id | NULL مسموح؛ مطلوب فعليًا لأدوار manager/agent كي يعمل نطاق القسم |
| is_active | TINYINT(1) DEFAULT 1 | |
| created_at | DATETIME | |

## departments
id INT PK AI | name_ar VARCHAR(100) | name_en VARCHAR(100)

ملاحظة نطاق: للأقسام واجهة إدارة مستقلة (admin_departments: إضافة/تعديل)، وتُستخدم لربط المستخدمين (manager/agent) والتصنيفات بالأقسام. الأساس لتوجيه التذاكر لمدير القسم.

## ticket_categories
id INT PK AI | name_ar VARCHAR(100) | name_en VARCHAR(100) | department_id INT FK→departments.id (NULL مسموح، ON DELETE SET NULL) | is_active TINYINT(1) DEFAULT 1

ملاحظة: department_id يربط التصنيف بقسم؛ مدير القسم يرى/يعالج كل تذكرة تصنيفها ضمن قسمه (نطاق مدير القسم = category.department_id == manager.department_id).

## ticket_priorities
id INT PK AI | name_ar VARCHAR(50) | name_en VARCHAR(50) | level INT (1=عاجل,2=متوسط,3=منخفض) | sla_hours INT | is_active TINYINT(1) DEFAULT 1

## ticket_statuses
id INT PK AI | code VARCHAR(20) UNIQUE (new/in_progress/on_hold/resolved/closed) | name_ar VARCHAR(50) | name_en VARCHAR(50)

## tickets
| الحقل | النوع | ملاحظات |
|---|---|---|
| id | INT PK AI | |
| ticket_number | VARCHAR(20) UNIQUE | صيغة TCK-YYYYMMDD-XXXX |
| title | VARCHAR(200) | |
| description | TEXT | |
| category_id | INT FK→ticket_categories.id | |
| priority_id | INT FK→ticket_priorities.id | |
| status_id | INT FK→ticket_statuses.id | افتراضي = new |
| created_by | INT FK→users.id | |
| assigned_to | INT FK→users.id | NULL مسموح |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| closed_at | DATETIME | NULL مسموح |

## ticket_comments
id INT PK AI | ticket_id INT FK→tickets.id | user_id INT FK→users.id | comment TEXT | created_at DATETIME

## ticket_attachments
id INT PK AI | ticket_id INT FK→tickets.id | comment_id INT FK→ticket_comments.id (NULL) | file_name VARCHAR(255) | file_path VARCHAR(255) | file_size INT | uploaded_by INT FK→users.id | uploaded_at DATETIME

## ticket_history
id INT PK AI | ticket_id INT FK→tickets.id | user_id INT FK→users.id | action VARCHAR(50) (create/status_change/assign/comment) | old_value VARCHAR(100) NULL | new_value VARCHAR(100) NULL | created_at DATETIME

## notifications
id INT PK AI | user_id INT FK→users.id | ticket_id INT FK→tickets.id | message_key VARCHAR(50) | is_read TINYINT(1) DEFAULT 0 | email_sent TINYINT(1) DEFAULT 0 | created_at DATETIME

ملاحظة: message_key مفتاح ترجمة (مثل notif_status_changed) يُعرض عبر t() مع ربطه ببيانات التذكرة (ticket_number) وقت العرض — لا يُخزَّن نص جاهز (التزامًا بقاعدة i18n في CLAUDE.md).

## الفهارس (Indexes)
- tickets: INDEX(status_id), INDEX(assigned_to), INDEX(created_by), INDEX(category_id)
- ticket_comments: INDEX(ticket_id)
- ticket_history: INDEX(ticket_id)
- notifications: INDEX(user_id, is_read)

## بيانات أولية مطلوبة (Seed)
- ticket_statuses: new, in_progress, on_hold, resolved, closed
- ticket_priorities: عاجل(level=1, sla=4), متوسط(level=2, sla=24), منخفض(level=3, sla=72)
- ticket_categories: هاردوير، سوفتوير، شبكة، إيميل، أخرى
- departments: قسم افتراضي واحد على الأقل
- users: مستخدم admin أولي للدخول الأول — employee_id = 'admin001' (يجب أن يطابق هذه القيمة حرفيًا أول سطر في مصفوفة LDAP mock، انظر SECURITY_AUTH.md)
