<?php

namespace App\Controllers\Api;

use App\Services\DashboardService;

/**
 * DashboardController
 *
 * Routes (protected by 'auth' filter):
 *   GET /api/v1/dashboard/summary  → summary()
 */
class DashboardController extends BaseApiController
{
    protected DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    /**
     * GET /api/v1/dashboard/summary
     *
     * Returns aggregated operational metrics for the dashboard.
     * All data is computed server-side in efficient single-query aggregations.
     *
     * Response shape:
     * {
     *   "success": true,
     *   "data": {
     *     "invoices": {
     *       "total": 12,
     *       "draft": 5,
     *       "sent": 4,
     *       "paid": 2,
     *       "overdue": 1,
     *       "outstanding_amount": 1500000.00,
     *       "total_invoiced": 2000000.00,
     *       "total_paid": 500000.00
     *     },
     *     "billing_items": {
     *       "draft_count": 8,
     *       "invoiced_count": 20,
     *       "draft_subtotal": 860000.00
     *     },
     *     "whatsapp": { "total_sent": 6 },
     *     "master": { "customers": 10, "ownerships": 25, "projects": 3 },
     *     "generated_at": "2026-08-21 09:30:00"
     *   }
     * }
     */
    public function summary()
    {
        return $this->success($this->service->getSummary());
    }
}
