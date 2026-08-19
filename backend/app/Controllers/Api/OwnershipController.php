<?php

namespace App\Controllers\Api;

use App\Services\OwnershipService;

class OwnershipController extends BaseApiController
{
    protected OwnershipService $service;

    public function __construct()
    {
        $this->service = new OwnershipService();
    }

    /**
     * GET /api/v1/ownerships
     * Filters: customer_id, project_id, ownership_type, active, search
     */
    public function index()
    {
        $filters = [
            'customer_id'    => $this->request->getGet('customer_id') ?? '',
            'project_id'     => $this->request->getGet('project_id')  ?? '',
            'ownership_type' => $this->request->getGet('ownership_type') ?? '',
            'active'         => $this->request->getGet('active') ?? '',
            'search'         => $this->request->getGet('search') ?? '',
        ];

        // Remove empty filter keys to avoid false WHERE clauses
        $filters = array_filter($filters, fn($v) => $v !== '');

        return $this->success($this->service->getAll($filters));
    }

    /**
     * GET /api/v1/ownerships/{id}
     */
    public function show(int $id)
    {
        $ownership = $this->service->getById($id);
        if (!$ownership) return $this->notFound('Kepemilikan tidak ditemukan.');
        return $this->success($ownership);
    }

    /**
     * POST /api/v1/ownerships
     */
    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'customer_id'    => 'required|integer',
            'project_id'     => 'required|integer',
            'ownership_type' => 'required|in_list[residential,commercial]',
            'area'           => 'required|decimal|greater_than[0]',
            'ipl_rate_id'    => 'required|integer',
            'start_date'     => 'required|valid_date',
            'end_date'       => 'permit_empty|valid_date',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $result = $this->service->create($body);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Kepemilikan berhasil dibuat.', 201);
    }

    /**
     * PUT /api/v1/ownerships/{id}
     */
    public function update(int $id)
    {
        $ownership = $this->service->getById($id);
        if (!$ownership) return $this->notFound('Kepemilikan tidak ditemukan.');

        $body  = $this->getBody();
        $rules = [
            'customer_id'    => 'permit_empty|integer',
            'project_id'     => 'permit_empty|integer',
            'ownership_type' => 'permit_empty|in_list[residential,commercial]',
            'area'           => 'permit_empty|decimal|greater_than[0]',
            'ipl_rate_id'    => 'permit_empty|integer',
            'start_date'     => 'permit_empty|valid_date',
            'end_date'       => 'permit_empty|valid_date',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $result = $this->service->update($id, $body);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Kepemilikan berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/ownerships/{id}
     */
    public function delete(int $id)
    {
        $ownership = $this->service->getById($id);
        if (!$ownership) return $this->notFound('Kepemilikan tidak ditemukan.');

        $this->service->delete($id);
        return $this->success(null, 'Kepemilikan berhasil dihapus.');
    }
}
