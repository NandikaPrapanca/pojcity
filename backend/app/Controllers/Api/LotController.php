<?php

namespace App\Controllers\Api;

use App\Services\HierarchyService;

class LotController extends BaseApiController
{
    protected HierarchyService $service;

    public function __construct()
    {
        $this->service = new HierarchyService();
    }

    public function index()
    {
        $filters = ['block_id' => $this->request->getGet('block_id') ?? ''];
        return $this->success($this->service->getLots($filters));
    }

    public function show(int $id)
    {
        $lot = $this->service->getLotById($id);
        if (!$lot) return $this->notFound();
        return $this->success($lot);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'block_id'   => 'required|integer',
            'lot_number' => 'required|max_length[50]',
            'area'       => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['block_id','lot_number','area','notes']));
        $result = $this->service->createLot($data);
        if (!$result['success']) return $this->error($result['error'], null, 422);
        return $this->success($result['data'], 'Lot/kavling berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $body  = $this->getBody();
        $rules = [
            'lot_number' => 'permit_empty|max_length[50]',
            'area'       => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['lot_number','area','notes']));
        $result = $this->service->updateLot($id, $data);
        if (!$result['success']) return $this->notFound($result['error']);
        return $this->success($result['data'], 'Lot/kavling berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $lot = $this->service->getLotById($id);
        if (!$lot) return $this->notFound();
        $this->service->deleteLot($id);
        return $this->success(null, 'Lot/kavling berhasil dihapus.');
    }
}
