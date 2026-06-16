<?php
/**
 * Arabic (ar) translation dictionary.
 *
 * Phase 3 expands the dictionary to cover the shared layout (navigation,
 * header/footer chrome) and the common interface vocabulary (statuses,
 * priorities, actions, table headers) reused across later phases. Keys must
 * stay in sync with lang/en.php.
 */

return [
    // App / chrome
    'app_name'          => 'نظام تذاكر الدعم التقني',

    // Language switcher
    'lang_name_ar'      => 'العربية',
    'lang_name_en'      => 'English',
    'lang_switch'       => 'تغيير اللغة',

    // Login page
    'login_title'       => 'تسجيل الدخول',
    'login_heading'     => 'تسجيل الدخول إلى النظام',
    'login_employee_id' => 'الرقم الوظيفي',
    'login_password'    => 'كلمة المرور',
    'login_submit'      => 'دخول',
    'login_error'       => 'الرقم الوظيفي أو كلمة المرور غير صحيحة.',
    'login_csrf_error'  => 'انتهت صلاحية الجلسة، يرجى المحاولة من جديد.',
    'session_expired'   => 'انتهت مهلة الجلسة، يرجى تسجيل الدخول مرة أخرى.',

    // Logout
    'logout'            => 'تسجيل الخروج',

    // Roles
    'role_employee'     => 'موظف',
    'role_agent'        => 'فني دعم',
    'role_admin'        => 'مدير النظام',

    // Dashboard
    'dashboard_title'   => 'لوحة التحكم',
    'welcome_user'      => 'مرحبًا، {name}',
    'dashboard_role'    => 'دورك في النظام: {role}',

    // Layout — navigation
    'nav_main'          => 'القائمة الرئيسية',
    'nav_toggle'        => 'إظهار/إخفاء القائمة',
    'nav_dashboard'     => 'لوحة التحكم',
    'nav_new_ticket'    => 'بلاغ جديد',
    'nav_my_tickets'    => 'بلاغاتي',
    'nav_tickets'       => 'التذاكر',
    'nav_users'         => 'المستخدمون',
    'nav_categories'    => 'الفئات',
    'nav_reports'       => 'التقارير',

    // Layout — header / footer
    'notifications'     => 'الإشعارات',
    'footer_copyright'  => '© {year} نظام تذاكر الدعم التقني — جميع الحقوق محفوظة.',

    // Ticket statuses
    'status_new'         => 'جديد',
    'status_in_progress' => 'قيد المعالجة',
    'status_on_hold'     => 'معلّق',
    'status_resolved'    => 'تم الحل',
    'status_closed'      => 'مغلق',

    // Ticket priorities
    'priority_urgent'   => 'عاجل',
    'priority_medium'   => 'متوسط',
    'priority_low'      => 'منخفض',

    // Common labels / table headers
    'label_ticket_number' => 'رقم التذكرة',
    'label_title'         => 'العنوان',
    'label_description'   => 'الوصف',
    'label_category'      => 'الفئة',
    'label_priority'      => 'الأولوية',
    'label_status'        => 'الحالة',
    'label_assigned_to'   => 'المسند إليه',
    'label_created_by'    => 'مقدّم البلاغ',
    'label_created_at'    => 'تاريخ الإنشاء',
    'label_updated_at'    => 'آخر تحديث',
    'label_attachment'    => 'مرفق',
    'label_actions'       => 'إجراءات',

    // Common actions
    'action_save'       => 'حفظ',
    'action_cancel'     => 'إلغاء',
    'action_close'      => 'إغلاق',
    'action_submit'     => 'إرسال',
    'action_view'       => 'عرض',
    'action_edit'       => 'تعديل',
    'action_delete'     => 'حذف',
    'action_back'       => 'رجوع',
    'action_search'     => 'بحث',
    'action_filter'     => 'تصفية',

    // Generic states
    'access_denied'     => 'ليست لديك صلاحية الوصول إلى هذه الصفحة.',
    'no_results'        => 'لا توجد نتائج لعرضها.',
    'loading'           => 'جارٍ التحميل…',
    'required_field'    => 'هذا الحقل مطلوب.',

    // Phase 4 — new ticket form
    'ticket_new_title'    => 'فتح بلاغ جديد',
    'ticket_submit'       => 'إرسال البلاغ',
    'select_placeholder'  => '— اختر —',
    'file_upload_hint'    => 'الملفات المسموحة: jpg, jpeg, png, pdf, docx, xlsx — الحجم الأقصى 5 ميغابايت.',
    'ticket_created'      => 'تم إنشاء البلاغ بنجاح، رقم التذكرة: {ticket_number}.',
    'ticket_save_error'   => 'تعذّر حفظ البلاغ، يرجى المحاولة مرة أخرى.',
    'err_title_too_long'  => 'العنوان طويل جدًا (الحد الأقصى 200 حرف).',
    'err_attachment_type' => 'نوع الملف غير مسموح.',
    'err_attachment_size' => 'حجم الملف يتجاوز الحد الأقصى المسموح (5 ميغابايت).',
    'err_attachment_upload' => 'تعذّر رفع الملف، يرجى المحاولة مرة أخرى.',

    // Phase 4 — my tickets list
    'ticket_my_title'       => 'بلاغاتي',
    'filter_all_statuses'   => 'كل الحالات',
    'filter_all_categories' => 'كل الفئات',
    'filter_all_priorities' => 'كل الأولويات',
    'pagination_label'      => 'تنقّل بين الصفحات',
    'pagination_prev'       => 'السابق',
    'pagination_next'       => 'التالي',
    'pagination_info'       => 'صفحة {current} من {total}',

    // Phase 5 — ticket detail, comments & attachments
    'unassigned'            => 'غير مُسند',
    'label_attachments'     => 'المرفقات',
    'no_attachments'        => 'لا توجد مرفقات.',
    'action_download'       => 'تنزيل',
    'comments_title'        => 'التعليقات',
    'no_comments'           => 'لا توجد تعليقات بعد.',
    'comment_add'           => 'إضافة تعليق',
    'label_comment'         => 'التعليق',
    'comment_submit'        => 'إرسال التعليق',
    'comment_added'         => 'تمت إضافة التعليق بنجاح.',
    'comment_save_error'    => 'تعذّر حفظ التعليق، يرجى المحاولة مرة أخرى.',
    'attachment_not_found'  => 'المرفق غير موجود أو لا تملك صلاحية الوصول إليه.',

    // Phase 6 — agent dashboard & ticket processing
    'agent_dashboard_title' => 'لوحة الفني',
    'assigned_to_me'        => 'مُسندة إليّ',
    'ticket_not_found'      => 'التذكرة غير موجودة أو لا تملك صلاحية الوصول إليها.',
    'agent_actions'         => 'إجراءات المعالجة',
    'agent_claim'           => 'تعيين لي',
    'agent_claim_hint'      => 'هذه التذكرة غير مُسندة. عيّنها لنفسك لتتمكن من معالجتها.',
    'agent_claim_first'     => 'يجب تعيين التذكرة لنفسك أولًا قبل تغيير حالتها.',
    'agent_change_status'   => 'تغيير الحالة',
    'agent_status_update'   => 'تحديث الحالة',
    'agent_assigned'        => 'تم تعيين التذكرة إليك بنجاح.',
    'agent_status_changed'  => 'تم تحديث حالة التذكرة بنجاح.',
    'agent_not_allowed'     => 'هذه التذكرة مُسندة إلى فني آخر.',
    'agent_action_error'    => 'تعذّر تنفيذ الإجراء، يرجى المحاولة مرة أخرى.',

    // Notifications (message_key values stored in the notifications table)
    'notif_status_changed'  => 'تم تحديث حالة التذكرة {ticket_number}.',
    'notif_assigned'        => 'تم تعيين فني للتذكرة {ticket_number}.',
    'notif_comment'         => 'تمت إضافة رد على التذكرة {ticket_number}.',

    // Phase 8 — notifications bell / dropdown
    'notif_empty'           => 'لا توجد إشعارات.',
    'notif_mark_all_read'   => 'تحديد الكل كمقروء',
    'notif_unread'          => 'لديك {count} إشعارات غير مقروءة',

    // Phase 7 — admin: shared
    'admin_save_error'        => 'تعذّر حفظ التغييرات، يرجى المحاولة مرة أخرى.',
    'admin_value_too_long'    => 'القيمة المدخلة طويلة جدًا.',
    'status_active'           => 'مُفعّل',
    'status_inactive'         => 'مُعطّل',
    'action_enable'           => 'تفعيل',
    'action_disable'          => 'تعطيل',

    // Phase 7 — admin: user management
    'admin_users_title'       => 'إدارة المستخدمين',
    'admin_user_add_title'    => 'إضافة مستخدم',
    'admin_user_edit_title'   => 'تعديل مستخدم',
    'admin_user_add'          => 'إضافة',
    'admin_user_created'      => 'تمت إضافة المستخدم بنجاح.',
    'admin_user_updated'      => 'تم تحديث بيانات المستخدم بنجاح.',
    'admin_user_toggled'      => 'تم تحديث حالة المستخدم بنجاح.',
    'admin_user_not_found'    => 'المستخدم غير موجود.',
    'admin_employee_id_taken' => 'الرقم الوظيفي مستخدم بالفعل.',
    'admin_email_taken'       => 'البريد الإلكتروني مستخدم بالفعل.',
    'admin_email_invalid'     => 'البريد الإلكتروني غير صالح.',
    'label_employee_id'       => 'الرقم الوظيفي',
    'label_full_name'         => 'الاسم الكامل',
    'label_email'             => 'البريد الإلكتروني',
    'label_role'              => 'الدور',
    'label_department'        => 'القسم',
    'label_account_status'    => 'حالة الحساب',
    'department_none'         => 'بدون قسم',
    'filter_all_roles'        => 'كل الأدوار',
    'filter_all_states'       => 'كل الحالات',

    // Phase 7 — admin: categories & priorities
    'admin_categories_title'      => 'إدارة الفئات والأولويات',
    'admin_categories_list_title' => 'الفئات',
    'admin_priorities_list_title' => 'الأولويات',
    'admin_category_add_title'    => 'إضافة فئة',
    'admin_category_edit_title'   => 'تعديل فئة',
    'admin_category_add'          => 'إضافة فئة',
    'admin_priority_add_title'    => 'إضافة أولوية',
    'admin_priority_edit_title'   => 'تعديل أولوية',
    'admin_priority_add'          => 'إضافة أولوية',
    'admin_category_saved'        => 'تم حفظ الفئة بنجاح.',
    'admin_priority_saved'        => 'تم حفظ الأولوية بنجاح.',
    'admin_level_invalid'         => 'المستوى يجب أن يكون بين 1 و3.',
    'admin_sla_invalid'           => 'مدة الـ SLA يجب أن تكون رقمًا أكبر من صفر.',
    'label_name_ar'               => 'الاسم (عربي)',
    'label_name_en'               => 'الاسم (إنكليزي)',
    'label_level'                 => 'المستوى',
    'label_sla_hours'             => 'مدة الـ SLA (ساعات)',

    // Admin — all-tickets overview
    'admin_tickets_title'   => 'كل التذاكر',
    'filter_all_agents'     => 'كل الفنيين',

    // Phase 7 — admin: ticket reassignment (on the ticket detail page)
    'admin_reassign_title'    => 'إعادة تعيين التذكرة',
    'admin_reassign_label'    => 'إسناد إلى فني',
    'admin_reassign_submit'   => 'إعادة التعيين',
    'admin_reassigned'        => 'تمت إعادة تعيين التذكرة بنجاح.',
    'admin_reassign_error'    => 'تعذّرت إعادة تعيين التذكرة، يرجى المحاولة مرة أخرى.',
    'admin_agent_invalid'     => 'يرجى اختيار فني صالح.',
    'admin_no_agents'         => 'لا يوجد فنيون متاحون للإسناد.',

    // Phase 9 — reports & statistics
    'admin_reports_title'     => 'التقارير والإحصائيات',
    'report_total_tickets'    => 'إجمالي التذاكر',
    'report_open_tickets'     => 'التذاكر المفتوحة',
    'report_overdue_tickets'  => 'التذاكر المتأخرة',
    'report_closed_tickets'   => 'التذاكر المغلقة',
    'report_avg_resolution'   => 'متوسط زمن الحل',
    'report_hours_value'      => '{hours} ساعة',
    'report_based_on'         => 'بناءً على {count} تذكرة مغلقة',
    'report_no_data'          => 'لا توجد بيانات',
    'report_by_status'        => 'التذاكر حسب الحالة',
    'report_by_category'      => 'التذاكر حسب الفئة',
    'report_by_agent'         => 'التذاكر حسب الفني',
    'report_ticket_count'     => 'عدد التذاكر',
    'report_overdue_title'    => 'التذاكر المتأخرة',
    'report_no_overdue'       => 'لا توجد تذاكر متأخرة. كل التذاكر ضمن مدة الـ SLA.',
    'report_sla_hours'        => 'الـ SLA (ساعة)',
    'report_age_hours'        => 'العمر (ساعة)',
    'report_overdue_by'       => 'التأخر (ساعة)',

    // Rebrand phase — bank identity & role-aware dashboard
    'bank_name'               => 'البنك الأهلي السوداني',
    'dash_total'              => 'إجمالي التذاكر',
    'dash_open'               => 'التذاكر المفتوحة',
    'dash_in_progress'        => 'قيد المعالجة',
    'dash_resolved'           => 'تم الحل',
    'dash_assigned_to_me'     => 'مُسندة إليّ',
    'dash_unassigned'         => 'غير مُسندة',
    'dash_overdue'            => 'التذاكر المتأخرة',
    'dash_avg_resolution'     => 'متوسط زمن الحل',
    'dash_hours_value'        => '{hours} ساعة',
    'dash_no_value'           => 'لا يوجد',
    'dash_recent_title'       => 'أحدث التذاكر',
    'dash_no_recent'          => 'لا توجد تذاكر بعد.',
];
