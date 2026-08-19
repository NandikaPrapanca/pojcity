<?php

namespace App\Controllers\Api;

use App\Services\HierarchyService;

class BlockController extends BaseApiController
{
    protected HierarchyService $service;

    public function __construct()
    {
        $this->service = new HierarchyService();
    }

    public function index()
    {
        $filters = ['cluster_id' => $this->request->getGet('cluster_id') ?? ''];
        return $this->success($this->service->getBlocks($filters));
    }

    public function show(int $id)
    {
        $block = $this->service->getBlockById($id);
        if (!$block) return $this->notFound();
        return $this->success($block);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'cluster_id' => 'required|integer',
            'name'       => 'required|max_length[100]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['cluster_id','name']));
        $result = $this->service->createBlock($data);
        if (!$result['success']) return $this->error($result['error'], null, 422);
        return $this->success($result['data'], 'Block berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $body  = $this->getBody();
        $rules = ['name' => 'permit_empty|max_length[100]'];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['name']));
        $result = $this->service->updateBlock($id, $data);
        if (!$result['success']) return $this->notFound($result['error']);
        return $this->success($result['data'], 'Block berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $block = $this->service->getBlockById($id);
        if (!$block) return $this->notFound();
        $this->service->deleteBlock($id);
        return $this->success(null, 'Block berhasil dihapus.');
    }

    public function lots(int $id)
    {
        $block = $this->service->getBlockById($id);
        if (!$block) return $this->notFound();
        return $this->success($this->service->getLotsByBlock($id));
    }
}
