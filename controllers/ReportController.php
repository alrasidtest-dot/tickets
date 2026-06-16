<?php
/**
 * ReportController — the administrator's reports & statistics dashboard.
 *
 * A single read-only page (?page=admin_reports) that aggregates ticket data:
 *   - distribution of tickets by status and by category (rendered as charts
 *     via the locally bundled public/assets/js/chart.min.js — no external CDN);
 *   - a per-agent breakdown of the workload;
 *   - the average resolution time (closed_at - created_at) over closed tickets;
 *   - the list of overdue tickets (open beyond their priority's SLA).
 *
 * All aggregation lives in the Ticket model (prepared statements only); this
 * controller just gathers the figures and hands them to the view. Admin-only.
 */
require_once MODELS_PATH . '/Ticket.php';

class ReportController
{
    /**
     * Build the reports dashboard: gather every statistic the view needs and
     * render it. No input is taken, so there is nothing to validate; the page
     * is guarded by the admin role only.
     *
     * @return void
     */
    public function index()
    {
        Auth::require('admin');

        // Distributions (status with zero-count rows preserved; categories too).
        $byStatus   = Ticket::countByStatus();
        $byCategory = Ticket::countByCategory();
        $byAgent    = Ticket::countByAgent();

        // Summary figures.
        $totals        = Ticket::statusTotals();
        $avgResolution = Ticket::avgResolution();

        // Overdue tickets (open beyond their priority's SLA).
        $overdue      = Ticket::findOverdue();
        $overdueCount = count($overdue);

        $pageTitle = Helpers::t('admin_reports_title');
        require VIEWS_PATH . '/admin/reports.php';
    }
}
