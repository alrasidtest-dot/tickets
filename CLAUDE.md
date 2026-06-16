# CLAUDE.md — IT Helpdesk Ticketing System

## نظرة عامة
نظام تذاكر دعم تقني لموظفي بنك لإرسال بلاغات لقسم IT ومتابعتها.
PHP خام (بدون framework) + PDO + MySQL، واجهة ثنائية اللغة (عربي RTL / إنكليزي).

## القواعد العامة (ثابتة لكل المراحل)
- الترميز UTF-8 في كل الملفات وقاعدة البيانات (utf8mb4).
- كل استعلام SQL عبر PDO Prepared Statements فقط — ممنوع تضمين متغيرات مباشرة.
- كل صفحة تتحقق من تسجيل الدخول والصلاحية (role) قبل أي عرض أو إجراء.
- كل نص ظاهر للمستخدم عبر دوال lang/ar.php و lang/en.php — لا نصوص ثابتة في الواجهات.
- تسمية الملفات: PascalCase للكلاسات (Models/Controllers/Core)، snake_case لملفات views.
- التعليقات داخل الكود بالإنكليزية.
- أي تعديل على قاعدة البيانات يجب أن يطابق docs/DATABASE.md حرفيًا (الأسماء والأنواع).

## البنية والتوجيه (Architecture & Routing)
- Document Root = public/ فقط (يحتوي index.php و assets/). كل ما عداه (core, models, controllers, views, lang, config, uploads) خارج public/ ويُستخدم عبر include من index.php فقط — غير قابل للوصول المباشر بالرابط.
- التوجيه: query-string فقط، الصيغة `index.php?page=KEY` (مثال: `?page=ticket_new`, `?page=ticket_view&id=5`)، وKEY بصيغة `{entity}_{action}`.
- core/Router.php: مصفوفة ثابتة `'page_key' => ['Controller', 'method']` فقط. كل مرحلة تضيف أسطرها هنا حصريًا — بدون آلية توجيه موازية.
- فحص الجلسة (`Auth::check()`) عام في index.php لكل page ما عدا `login`. فحص الدور الدقيق (`Auth::require`/`requireAny`) مسؤولية كل Controller method حصريًا — لا فحص أدوار في index.php أو Router.php.
- الملفات المرفوعة (uploads/) خارج public/ — تُعرض فقط عبر `index.php?page=download&id={attachment_id}`: الـController يستعلم عن المرفق بالـ id، يتحقق من صلاحية المستخدم على التذكرة المرتبطة، ثم يبث file_path من DB — ممنوع تمرير مسار الملف عبر $_GET.

## فهرس ملفات التوجيه
| الملف | اقرأه عند... |
|---|---|
| docs/DATABASE.md | أي عملية تتعلق بجداول/استعلامات قاعدة البيانات |
| docs/PROJECT_STRUCTURE.md | إنشاء ملفات/مجلدات جديدة أو معرفة مكان ملف |
| docs/BACKEND_GUIDE.md | كتابة Models/Controllers/منطق PHP |
| docs/FRONTEND_GUIDE.md | كتابة Views/CSS/HTML |
| docs/SECURITY_AUTH.md | المصادقة، الجلسات، الصلاحيات، رفع الملفات |
| docs/PHASES.md | معرفة المرحلة الحالية ومعايير القبول |

## تعليمات التنفيذ
- اقرأ فقط هذا الملف + الملفات المذكورة في برومت المرحلة الحالية. لا تقرأ ملفات docs غير المذكورة.
- بعد إنهاء المرحلة، حدّث رمز الحالة (⬜→✅) في docs/PHASES.md للمرحلة المنفذة فقط.
- عند تعارض بين هذا الملف وملف آخر، هذا الملف هو المرجع الأعلى.
- لا تنشئ ملفات أو جداول أو حقول غير موجودة في docs/ بدون ذكر ذلك صريحًا في ردك.
