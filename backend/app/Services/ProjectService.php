<?php

namespace App\Services;

use App\Models\ProjectModel;
use App\Models\ClusterModel;

class ProjectService
{
    protected ProjectModel $model;
    protected ClusterModel $clusterModel;

    public function __construct()
    {
        $this->model        = new ProjectModel();
        $this->clusterModel = new ClusterModel();
    }

    public function getAll(array $filters = []): array
    {
        $builder = $this->model->builder();
        $builder->where('deleted_at IS NULL');

        if (!empty($filters['project_type'])) {
            $builder->where('project_type', $filters['project_type']);
        }

        if (!empty($filters['company_id'])) {
            $builder->where('company_id', (int) $filters['company_id']);
        }

        if (!empty($filters['search'])) {
            $builder->like('name', $filters['search']);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    public function getById(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function create(array $data): array
    {
        $id = $this->model->insert($data, true);
        return $this->model->find($id);
    }

    public function update(int $id, array $data): ?array
    {
        $project = $this->model->find($id);
        if (!$project) return null;

        $this->model->update($id, $data);
        return $this->model->find($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    public function getClusters(int $projectId): array
    {
        return $this->clusterModel
            ->where('project_id', $projectId)
            ->where('deleted_at IS NULL')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
