<?php

namespace App\Services;

use Config\Database;

/**
 * DashboardService
 *
 * Aggregates key operational metrics from the database for the dashboard.
 * All queries run as single-pass aggregations — no N+1 issues.
 *
 * Metrics returned by getSummary():
 *  ── Invoices ────────────────────────────────────────────────────
 *  total_invoices          Total invoice rows (non-deleted)
 *  invoices_draft          Invoices with status = 'draft'
 *  invoices_sent           Invoices with status = 'sent'
 *  invoices_paid           Invoices with status = 'paid'
 *  invoices_overdue        Invoices with status = 'overdue'
 *  outstanding_amount      Sum of grand_total for non-paid, non-cancelled invoices
 *  total_invoiced_amount   Sum of grand_total for all non-deleted invoices
 *  total_paid_amount       Sum of grand_total for status = 'paid'
 *
 *  ── Billing Items ───────────────────────────────────────────────
 *  billing_items_draft     Count of billing_items with status = 'draft'
 *  billing_items_invoiced  Count of billing_items with status = 'invoiced'
 *  draft_subtotal          Sum of subtotal for draft billing items
 *
 *  ── WhatsApp ────────────────────────────────────────────────────
 *  whatsapp_sent           Count of whatsapp_logs rows (all statuses)
 *
 *  ── Master Data (for context) ───────────────────────────────────
 *  total_customers         Active customers
 *  total_ownerships        Active ownerships
 *  total_projects          Active projects
 */
class DashboardService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Return all dashboard summary metrics in a single call.
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'invoices'        => $this->getInvoiceMetrics(),
            'billing_items'   => $this->getBillingItemMetrics(),
            'whatsapp'        => $this->getWhatsAppMetrics(),
            'master'          => $this->getMasterDataCounts(),
            'monthly_trends'  => $this->getMonthlyRevenueTrend(),
            'generated_at'    => date('Y-m-d H:i:s'),
        ];
    }

    // =========================================================================
    // Private aggregation methods
    // =========================================================================

    private function getInvoiceMetrics(): array
    {
        // Single-query aggregation for all invoice stats
        $row = $this->db->query("
            SELECT
                COUNT(*)                                                AS total_invoices,
                SUM(CASE WHEN status = 'draft'     THEN 1 ELSE 0 END)  AS invoices_draft,
                SUM(CASE WHEN status = 'sent'      THEN 1 ELSE 0 END)  AS invoices_sent,
                SUM(CASE WHEN status = 'paid'      THEN 1 ELSE 0 END)  AS invoices_paid,
                SUM(CASE WHEN status = 'overdue'   THEN 1 ELSE 0 END)  AS invoices_overdue,
                SUM(CASE WHEN status NOT IN ('paid','cancelled') THEN grand_total ELSE 0 END) AS outstanding_amount,
                SUM(CASE WHEN status != 'cancelled' THEN grand_total ELSE 0 END)              AS total_invoiced_amount,
                SUM(CASE WHEN status = 'paid'       THEN grand_total ELSE 0 END)              AS total_paid_amount
            FROM invoices
            WHERE deleted_at IS NULL
        ")->getRowArray();

        return [
            'total'              => (int)($row['total_invoices'] ?? 0),
            'draft'              => (int)($row['invoices_draft'] ?? 0),
            'sent'               => (int)($row['invoices_sent'] ?? 0),
            'paid'               => (int)($row['invoices_paid'] ?? 0),
            'overdue'            => (int)($row['invoices_overdue'] ?? 0),
            'outstanding_amount' => (float)($row['outstanding_amount'] ?? 0),
            'total_invoiced'     => (float)($row['total_invoiced_amount'] ?? 0),
            'total_paid'         => (float)($row['total_paid_amount'] ?? 0),
        ];
    }

    private function getBillingItemMetrics(): array
    {
        $row = $this->db->query("
            SELECT
                SUM(CASE WHEN status = 'draft'    THEN 1 ELSE 0 END)       AS draft_count,
                SUM(CASE WHEN status = 'invoiced' THEN 1 ELSE 0 END)       AS invoiced_count,
                SUM(CASE WHEN status = 'draft'    THEN subtotal ELSE 0 END) AS draft_subtotal
            FROM billing_items
            WHERE deleted_at IS NULL
        ")->getRowArray();

        return [
            'draft_count'    => (int)($row['draft_count'] ?? 0),
            'invoiced_count' => (int)($row['invoiced_count'] ?? 0),
            'draft_subtotal' => (float)($row['draft_subtotal'] ?? 0),
        ];
    }

    private function getWhatsAppMetrics(): array
    {
        $row = $this->db->query("
            SELECT COUNT(*) AS total_sent FROM whatsapp_logs
        ")->getRowArray();

        return [
            'total_sent' => (int)($row['total_sent'] ?? 0),
        ];
    }

    private function getMasterDataCounts(): array
    {
        $row = $this->db->query("
            SELECT
                (SELECT COUNT(*) FROM customers   WHERE deleted_at IS NULL) AS customers,
                (SELECT COUNT(*) FROM ownerships  WHERE deleted_at IS NULL) AS ownerships,
                (SELECT COUNT(*) FROM projects    WHERE deleted_at IS NULL) AS projects
        ")->getRowArray();

        return [
            'customers'  => (int)($row['customers']  ?? 0),
            'ownerships' => (int)($row['ownerships'] ?? 0),
            'projects'   => (int)($row['projects']   ?? 0),
        ];
    }

    private function getMonthlyRevenueTrend(): array
    {
        $rows = $this->db->query("
            SELECT
                DATE_FORMAT(issue_date, '%Y-%m') AS period,
                DATE_FORMAT(issue_date, '%b %Y') AS period_label,
                SUM(CASE WHEN status = 'paid' THEN grand_total ELSE 0 END) AS paid_revenue,
                SUM(CASE WHEN status NOT IN ('paid', 'cancelled') THEN grand_total ELSE 0 END) AS outstanding_revenue,
                SUM(grand_total) AS total_invoiced,
                COUNT(*) AS count_invoices
            FROM invoices
            WHERE deleted_at IS NULL
              AND issue_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(issue_date, '%Y-%m'), DATE_FORMAT(issue_date, '%b %Y')
            ORDER BY period ASC
        ")->getResultArray();

        if (empty($rows)) {
            $currentPeriod = date('Y-m');
            $currentLabel  = date('M Y');
            return [
                [
                    'period'              => $currentPeriod,
                    'label'               => $currentLabel,
                    'paid_revenue'        => 0.0,
                    'outstanding_revenue' => 0.0,
                    'total_invoiced'      => 0.0,
                    'count_invoices'      => 0,
                ]
            ];
        }

        return array_map(function($r) {
            return [
                'period'              => $r['period'],
                'label'               => $r['period_label'],
                'paid_revenue'        => (float)($r['paid_revenue'] ?? 0),
                'outstanding_revenue' => (float)($r['outstanding_revenue'] ?? 0),
                'total_invoiced'      => (float)($r['total_invoiced'] ?? 0),
                'count_invoices'      => (int)($r['count_invoices'] ?? 0),
            ];
        }, $rows);
    }
}
