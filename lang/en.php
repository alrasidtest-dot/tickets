<?php
/**
 * English (en) translation dictionary.
 *
 * Phase 3 expands the dictionary to cover the shared layout (navigation,
 * header/footer chrome) and the common interface vocabulary (statuses,
 * priorities, actions, table headers) reused across later phases. Keys must
 * stay in sync with lang/ar.php.
 */

return [
    // App / chrome
    'app_name'          => 'IT Helpdesk Ticketing System',

    // Language switcher
    'lang_name_ar'      => 'العربية',
    'lang_name_en'      => 'English',
    'lang_switch'       => 'Change language',

    // Login page
    'login_title'       => 'Sign in',
    'login_heading'     => 'Sign in to the system',
    'login_employee_id' => 'Employee ID',
    'login_password'    => 'Password',
    'login_submit'      => 'Sign in',
    'login_error'       => 'Invalid employee ID or password.',
    'login_csrf_error'  => 'Your session has expired, please try again.',
    'session_expired'   => 'Your session timed out, please sign in again.',

    // Logout
    'logout'            => 'Sign out',

    // Roles
    'role_employee'     => 'Employee',
    'role_agent'        => 'Support Agent',
    'role_admin'        => 'Administrator',

    // Dashboard
    'dashboard_title'   => 'Dashboard',
    'welcome_user'      => 'Welcome, {name}',
    'dashboard_role'    => 'Your role: {role}',

    // Layout — navigation
    'nav_main'          => 'Main menu',
    'nav_toggle'        => 'Toggle menu',
    'nav_dashboard'     => 'Dashboard',
    'nav_new_ticket'    => 'New ticket',
    'nav_my_tickets'    => 'My tickets',
    'nav_tickets'       => 'Tickets',
    'nav_users'         => 'Users',
    'nav_categories'    => 'Categories',
    'nav_reports'       => 'Reports',

    // Layout — header / footer
    'notifications'     => 'Notifications',
    'footer_copyright'  => '© {year} IT Helpdesk Ticketing System — All rights reserved.',

    // Ticket statuses
    'status_new'         => 'New',
    'status_in_progress' => 'In progress',
    'status_on_hold'     => 'On hold',
    'status_resolved'    => 'Resolved',
    'status_closed'      => 'Closed',

    // Ticket priorities
    'priority_urgent'   => 'Urgent',
    'priority_medium'   => 'Medium',
    'priority_low'      => 'Low',

    // Common labels / table headers
    'label_ticket_number' => 'Ticket no.',
    'label_title'         => 'Title',
    'label_description'   => 'Description',
    'label_category'      => 'Category',
    'label_priority'      => 'Priority',
    'label_status'        => 'Status',
    'label_assigned_to'   => 'Assigned to',
    'label_created_by'    => 'Reported by',
    'label_created_at'    => 'Created at',
    'label_updated_at'    => 'Last updated',
    'label_attachment'    => 'Attachment',
    'label_actions'       => 'Actions',

    // Common actions
    'action_save'       => 'Save',
    'action_cancel'     => 'Cancel',
    'action_close'      => 'Close',
    'action_submit'     => 'Submit',
    'action_view'       => 'View',
    'action_edit'       => 'Edit',
    'action_delete'     => 'Delete',
    'action_back'       => 'Back',
    'action_search'     => 'Search',
    'action_filter'     => 'Filter',

    // Generic states
    'access_denied'     => 'You do not have permission to access this page.',
    'no_results'        => 'No results to display.',
    'loading'           => 'Loading…',
    'required_field'    => 'This field is required.',

    // Phase 4 — new ticket form
    'ticket_new_title'    => 'Open a new ticket',
    'ticket_submit'       => 'Submit ticket',
    'select_placeholder'  => '— Select —',
    'file_upload_hint'    => 'Allowed files: jpg, jpeg, png, pdf, docx, xlsx — max size 5 MB.',
    'ticket_created'      => 'Ticket created successfully. Ticket number: {ticket_number}.',
    'ticket_save_error'   => 'Could not save the ticket, please try again.',
    'err_title_too_long'  => 'The title is too long (maximum 200 characters).',
    'err_attachment_type' => 'This file type is not allowed.',
    'err_attachment_size' => 'The file exceeds the maximum allowed size (5 MB).',
    'err_attachment_upload' => 'Could not upload the file, please try again.',

    // Phase 4 — my tickets list
    'ticket_my_title'       => 'My tickets',
    'filter_all_statuses'   => 'All statuses',
    'filter_all_categories' => 'All categories',
    'filter_all_priorities' => 'All priorities',
    'pagination_label'      => 'Page navigation',
    'pagination_prev'       => 'Previous',
    'pagination_next'       => 'Next',
    'pagination_info'       => 'Page {current} of {total}',

    // Phase 5 — ticket detail, comments & attachments
    'unassigned'            => 'Unassigned',
    'label_attachments'     => 'Attachments',
    'no_attachments'        => 'No attachments.',
    'action_download'       => 'Download',
    'comments_title'        => 'Comments',
    'no_comments'           => 'No comments yet.',
    'comment_add'           => 'Add a comment',
    'label_comment'         => 'Comment',
    'comment_submit'        => 'Post comment',
    'comment_added'         => 'Your comment was added.',
    'comment_save_error'    => 'Could not save the comment, please try again.',
    'attachment_not_found'  => 'The attachment does not exist or you are not allowed to access it.',

    // Phase 6 — agent dashboard & ticket processing
    'agent_dashboard_title' => 'Agent dashboard',
    'assigned_to_me'        => 'Assigned to me',
    'ticket_not_found'      => 'The ticket does not exist or you are not allowed to access it.',
    'agent_actions'         => 'Processing actions',
    'agent_claim'           => 'Assign to me',
    'agent_claim_hint'      => 'This ticket is unassigned. Assign it to yourself to start working on it.',
    'agent_claim_first'     => 'Assign the ticket to yourself before changing its status.',
    'agent_change_status'   => 'Change status',
    'agent_status_update'   => 'Update status',
    'agent_assigned'        => 'The ticket was assigned to you.',
    'agent_status_changed'  => 'The ticket status was updated.',
    'agent_not_allowed'     => 'This ticket is assigned to another agent.',
    'agent_action_error'    => 'Could not complete the action, please try again.',

    // Notifications (message_key values stored in the notifications table)
    'notif_status_changed'  => 'The status of ticket {ticket_number} was updated.',
    'notif_assigned'        => 'An agent was assigned to ticket {ticket_number}.',
    'notif_comment'         => 'A new reply was added to ticket {ticket_number}.',

    // Phase 8 — notifications bell / dropdown
    'notif_empty'           => 'No notifications.',
    'notif_mark_all_read'   => 'Mark all as read',
    'notif_unread'          => 'You have {count} unread notifications',

    // Phase 7 — admin: shared
    'admin_save_error'        => 'Could not save the changes, please try again.',
    'admin_value_too_long'    => 'The value entered is too long.',
    'status_active'           => 'Active',
    'status_inactive'         => 'Inactive',
    'action_enable'           => 'Enable',
    'action_disable'          => 'Disable',

    // Phase 7 — admin: user management
    'admin_users_title'       => 'User management',
    'admin_user_add_title'    => 'Add a user',
    'admin_user_edit_title'   => 'Edit user',
    'admin_user_add'          => 'Add',
    'admin_user_created'      => 'The user was created.',
    'admin_user_updated'      => 'The user was updated.',
    'admin_user_toggled'      => 'The user status was updated.',
    'admin_user_not_found'    => 'User not found.',
    'admin_employee_id_taken' => 'That employee ID is already in use.',
    'admin_email_taken'       => 'That email is already in use.',
    'admin_email_invalid'     => 'The email address is not valid.',
    'label_employee_id'       => 'Employee ID',
    'label_full_name'         => 'Full name',
    'label_email'             => 'Email',
    'label_role'              => 'Role',
    'label_department'        => 'Department',
    'label_account_status'    => 'Account status',
    'department_none'         => 'No department',
    'filter_all_roles'        => 'All roles',
    'filter_all_states'       => 'All states',

    // Phase 7 — admin: categories & priorities
    'admin_categories_title'      => 'Categories & priorities',
    'admin_categories_list_title' => 'Categories',
    'admin_priorities_list_title' => 'Priorities',
    'admin_category_add_title'    => 'Add a category',
    'admin_category_edit_title'   => 'Edit category',
    'admin_category_add'          => 'Add category',
    'admin_priority_add_title'    => 'Add a priority',
    'admin_priority_edit_title'   => 'Edit priority',
    'admin_priority_add'          => 'Add priority',
    'admin_category_saved'        => 'The category was saved.',
    'admin_priority_saved'        => 'The priority was saved.',
    'admin_level_invalid'         => 'The level must be between 1 and 3.',
    'admin_sla_invalid'           => 'The SLA must be a number greater than zero.',
    'label_name_ar'               => 'Name (Arabic)',
    'label_name_en'               => 'Name (English)',
    'label_level'                 => 'Level',
    'label_sla_hours'             => 'SLA (hours)',

    // Admin — all-tickets overview
    'admin_tickets_title'   => 'All tickets',
    'filter_all_agents'     => 'All agents',

    // Phase 7 — admin: ticket reassignment (on the ticket detail page)
    'admin_reassign_title'    => 'Reassign ticket',
    'admin_reassign_label'    => 'Assign to agent',
    'admin_reassign_submit'   => 'Reassign',
    'admin_reassigned'        => 'The ticket was reassigned.',
    'admin_reassign_error'    => 'Could not reassign the ticket, please try again.',
    'admin_agent_invalid'     => 'Please choose a valid agent.',
    'admin_no_agents'         => 'There are no agents available to assign.',

    // Phase 9 — reports & statistics
    'admin_reports_title'     => 'Reports & statistics',
    'report_total_tickets'    => 'Total tickets',
    'report_open_tickets'     => 'Open tickets',
    'report_overdue_tickets'  => 'Overdue tickets',
    'report_closed_tickets'   => 'Closed tickets',
    'report_avg_resolution'   => 'Average resolution time',
    'report_hours_value'      => '{hours} h',
    'report_based_on'         => 'over {count} closed tickets',
    'report_no_data'          => 'No data',
    'report_by_status'        => 'Tickets by status',
    'report_by_category'      => 'Tickets by category',
    'report_by_agent'         => 'Tickets by agent',
    'report_ticket_count'     => 'Ticket count',
    'report_overdue_title'    => 'Overdue tickets',
    'report_no_overdue'       => 'No overdue tickets. Everything is within SLA.',
    'report_sla_hours'        => 'SLA (h)',
    'report_age_hours'        => 'Age (h)',
    'report_overdue_by'       => 'Overdue by (h)',

    // Rebrand phase — bank identity & role-aware dashboard
    'bank_name'               => 'Sudanese National Bank',
    'dash_total'              => 'Total tickets',
    'dash_open'               => 'Open tickets',
    'dash_in_progress'        => 'In progress',
    'dash_resolved'           => 'Resolved',
    'dash_assigned_to_me'     => 'Assigned to me',
    'dash_unassigned'         => 'Unassigned',
    'dash_overdue'            => 'Overdue tickets',
    'dash_avg_resolution'     => 'Avg. resolution',
    'dash_hours_value'        => '{hours} h',
    'dash_no_value'           => 'N/A',
    'dash_recent_title'       => 'Recent tickets',
    'dash_no_recent'          => 'No tickets yet.',
];
