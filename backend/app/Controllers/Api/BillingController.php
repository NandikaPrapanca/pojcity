<?php

namespace App\Controllers\Api;

use App\Services\BillingService;

class BillingController extends BaseApiController
{
    protected BillingService $service;

    public function __construct()
    {
        $this->service = new BillingService();
    }

    /**
     * GET /api/v1/billing-items
     */
    public function index()
    {
        $filters = [
            'ownership_id'         => $this->request->getGet('ownership_id') ?? '',
            'billing_type'         => $this->request->getGet('billing_type') ?? '',
            'status'               => $this->request->getGet('status') ?? '',
            'billing_period_start' => $this->request->getGet('billing_period_start') ?? '',
            'billing_period_end'   => $this->request->getGet('billing_period_end') ?? '',
            'period'               => $this->request->getGet('period') ?? '',
            'project_id'           => $this->request->getGet('project_id') ?? '',
            'search'               => $this->request->getGet('search') ?? '',
        ];

        $filters = array_filter($filters, fn($v) => $v !== '');

        return $this->success($this->service->getAll($filters));
    }

    /**
     * GET /api/v1/billing-items/{id}
     */
    public function show(int $id)
    {
        $item = $this->service->getById($id);
        if (!$item) {
            return $this->notFound('Item tagihan tidak ditemukan.');
        }

        return $this->success($item);
    }

    /**
     * POST /api/v1/billing-items
     */
    public function create()
    {
        $body = $this->getBody();

        $rules = [
            'ownership_id'         => 'required|integer',
            'billing_type'         => 'required|in_list[ipl,water,electricity,other,IPL,WATER,ELECTRICITY,OTHER]',
            'billing_period_start' => 'required|valid_date',
            'billing_period_end'   => 'required|valid_date',
            'description'          => 'required',
            'quantity'             => 'permit_empty|decimal',
            'unit'                 => 'permit_empty|max_length[50]',
            'unit_price'           => 'permit_empty|decimal',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $result = $this->service->create($body);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Item tagihan berhasil disimpan.', 201);
    }

    /**
     * PUT /api/v1/billing-items/{id}
     */
    public function update(int $id)
    {
        $body = $this->getBody();

        $rules = [
            'ownership_id'         => 'permit_empty|integer',
            'billing_type'         => 'permit_empty|in_list[ipl,water,electricity,other,IPL,WATER,ELECTRICITY,OTHER]',
            'billing_period_start' => 'permit_empty|valid_date',
            'billing_period_end'   => 'permit_empty|valid_date',
            'description'          => 'permit_empty',
            'quantity'             => 'permit_empty|decimal',
            'unit'                 => 'permit_empty|max_length[50]',
            'unit_price'           => 'permit_empty|decimal',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $result = $this->service->update($id, $body);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Item tagihan berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/billing-items/{id}
     */
    public function delete(int $id)
    {
        $result = $this->service->delete($id);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success(null, 'Item tagihan berhasil dihapus/dibatalkan.');
    }

    /**
     * POST /api/v1/billing/generate-ipl
     *
     * Generate an IPL billing item automatically from ownership configuration.
     *
     * Request body:
     * {
     *   "ownership_id": 1,
     *   "billing_period_start": "2026-07-01",
     *   "billing_period_end":   "2026-08-01",
     *   "notes": "optional"           ← optional
     * }
     *
     * Response 201: Generated billing_items row (enriched with ownership relations)
     * Response 422: Validation or business rule failure
     */
    public function generateIpl()
    {
        $body = $this->getBody();

        $rules = [
            'ownership_id'         => 'required|integer',
            'billing_period_start' => 'required|valid_date',
            'billing_period_end'   => 'required|valid_date',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $result = $this->service->generateIpl($body);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Tagihan IPL berhasil digenerate.', 201);
    }
}
