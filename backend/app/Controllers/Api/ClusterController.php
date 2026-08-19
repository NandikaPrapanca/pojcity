<?php

namespace App\Controllers\Api;

use App\Services\HierarchyService;

class ClusterController extends BaseApiController
{
    protected HierarchyService $service;

    public function __construct()
    {
        $this->service = new HierarchyService();
    }

    public function index()
    {
        $filters = ['project_id' => $this->request->getGet('project_id') ?? ''];
        return $this->success($this->service->getClusters($filters));
    }

    public function show(int $id)
    {
        $cluster = $this->service->getClusterById($id);
        if (!$cluster) return $this->notFound();
        return $this->success($cluster);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'project_id' => 'required|integer',
            'name'       => 'required|max_length[255]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['project_id','name']));
        $result = $this->service->createCluster($data);
        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }
        return $this->success($result['data'], 'Cluster berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $body  = $this->getBody();
        $rules = ['name' => 'permit_empty|max_length[255]'];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['name']));
        $result = $this->service->updateCluster($id, $data);
        if (!$result['success']) return $this->notFound($result['error']);
        return $this->success($result['data'], 'Cluster berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $cluster = $this->service->getClusterById($id);
        if (!$cluster) return $this->notFound();
        $this->service->deleteCluster($id);
        return $this->success(null, 'Cluster berhasil dihapus.');
    }

    public function blocks(int $id)
    {
        $cluster = $this->service->getClusterById($id);
        if (!$cluster) return $this->notFound();
        return $this->success($this->service->getBlocksByCluster($id));
    }
}
