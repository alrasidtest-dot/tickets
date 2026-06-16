# FRONTEND_GUIDE.md — دورك: Senior Frontend Developer

## Design Tokens (في public/assets/css/style.css ضمن :root)
| المتغير | القيمة | الاستخدام |
|---|---|---|
| --color-primary | #1A56DB | أزرار/روابط أساسية |
| --color-primary-dark | #1741A6 | hover للعناصر الأساسية |
| --color-secondary | #64748B | نصوص ثانوية |
| --color-success | #16A34A | حالة "تم الحل" |
| --color-warning | #D97706 | أولوية متوسطة |
| --color-danger | #DC2626 | أولوية عاجلة / أخطاء |
| --color-bg | #F8FAFC | خلفية الصفحة |
| --color-surface | #FFFFFF | بطاقات وجداول |
| --color-border | #E2E8F0 | الحدود |
| --color-text | #1E293B | نص أساسي |
| --color-text-muted | #64748B | نص ثانوي |
| --radius | 8px | زوايا العناصر |
| --shadow-card | 0 1px 3px rgba(0,0,0,.1) | ظل البطاقات |
| --font-ar | 'Tajawal', sans-serif | الخط عند lang=ar |
| --font-en | 'Inter', sans-serif | الخط عند lang=en |
| --spacing-unit | 8px | وحدة المسافات (مضاعفاتها) |

## ربط الحالات والأولويات بالألوان (badge_status / badge_priority)
| القيمة | المتغير |
|---|---|
| status: new | --color-primary |
| status: in_progress | --color-warning |
| status: on_hold | --color-secondary |
| status: resolved | --color-success |
| status: closed | --color-text-muted |
| priority: عاجل (level=1) | --color-danger |
| priority: متوسط (level=2) | --color-warning |
| priority: منخفض (level=3) | --color-secondary |

## قواعد التخطيط
- Layout: Sidebar ثابت 240px + منطقة محتوى؛ يطوى الـ Sidebar تحت عرض 768px.
- `<html dir="rtl" lang="ar">` أو `dir="ltr" lang="en"` حسب لغة الجلسة.
- كل صفحة تُضمّن layout/header.php + layout/sidebar.php + layout/footer.php عبر include.
- الجداول: صفوف متناوبة الخلفية + badge ملوّن للحالة والأولوية حسب design tokens.
- النماذج: label فوق كل حقل، رسائل الخطأ بـ --color-danger أسفل الحقل مباشرة.
- الأزرار: كلاسات .btn .btn-primary .btn-outline .btn-danger فقط.
- الأيقونات: SVG inline موحدة، بدون مكتبات أيقونات خارجية ثقيلة.
- أي نص ظاهر عبر `t($key, $params = [])` من lang/ — تستبدل `{param}` في نص الترجمة بقيم $params (مثال: `t('notif_status_changed', ['ticket_number' => 'TCK-...'])`). ممنوع نص ثابت داخل HTML.
- الخطوط (Tajawal/Inter): تحميل محلي داخل public/assets/fonts/ بدون CDN خارجي (توافق شبكة البنك الداخلية)، مع fallback: system-ui, Arial, sans-serif.
- صفحة login.php: اللغة الافتراضية = عربي (ar/RTL)، مع مبدّل لغة يحفظ القيمة في session بنفس مفتاح lang المستخدم بعد تسجيل الدخول.

## المكونات المطلوبة (views/components/)
badge_status.php, badge_priority.php, pagination.php, alert.php, file_upload.php
