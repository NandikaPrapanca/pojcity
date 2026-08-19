<?php

namespace App\Services;

use App\Models\CompanyModel;

class CompanyService
{
    protected CompanyModel $model;

    public function __construct()
    {
        $this->model = new CompanyModel();
    }

    public function getAll(): array
    {
        return $this->model->findAll();
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

    public function update(int $id, array $data): array
    {
        $this->model->update($id, $data);
        return $this->model->find($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }
}
