<?php

namespace App\Controllers\Api;

use App\Services\ProjectService;

class ProjectController extends BaseApiController
{
    protected ProjectService $service;

    public function __construct()
    {
        $this->service = new ProjectService();
    }

    public function index()
    {
        $filters = [
            'project_type' => $this->request->getGet('project_type') ?? '',
            'company_id'   => $this->request->getGet('company_id') ?? '',
            'search'       => $this->request->getGet('search') ?? '',
        ];
        return $this->success($this->service->getAll($filters));
    }

    public function show(int $id)
    {
        $project = $this->service->getById($id);
        if (!$project) return $this->notFound();
        return $this->success($project);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'name'         => 'required|max_length[255]',
            'project_type' => 'required|in_list[residential,commercial]',
            'company_id'   => 'required|integer',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['company_id','name','project_type','address','notes'];
        $data   = array_intersect_key($body, array_flip($fields));
        return $this->success($this->service->create($data), 'Proyek berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $project = $this->service->getById($id);
        if (!$project) return $this->notFound();

        $body  = $this->getBody();
        $rules = [
            'name'         => 'permit_empty|max_length[255]',
            'project_type' => 'permit_empty|in_list[residential,commercial]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['company_id','name','project_type','address','notes'];
        $data   = array_intersect_key($body, array_flip($fields));
        return $this->success($this->service->update($id, $data), 'Proyek berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $project = $this->service->getById($id);
        if (!$project) return $this->notFound();

        $this->service->delete($id);
        return $this->success(null, 'Proyek berhasil dihapus.');
    }

    public function clusters(int $id)
    {
        $project = $this->service->getById($id);
        if (!$project) return $this->notFound();
        return $this->success($this->service->getClusters($id));
    }
}
