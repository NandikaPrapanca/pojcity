<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\PicModel;

class CustomerService
{
    protected CustomerModel $model;
    protected PicModel $picModel;

    public function __construct()
    {
        $this->model    = new CustomerModel();
        $this->picModel = new PicModel();
    }

    /**
     * Get paginated customers with optional search/filter.
     *
     * Supported filters:
     *   search        - searches name or whatsapp
     *   customer_type - filters by type
     *   page          - page number (default 1)
     *   per_page      - items per page (default 20)
     */
    public function getAll(array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page    = (int) ($filters['page'] ?? 1);

        $builder = $this->model->builder();
        $builder->where('customers.deleted_at IS NULL');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->groupStart()
                    ->like('name', $search)
                    ->orLike('whatsapp', $search)
                    ->groupEnd();
        }

        if (!empty($filters['customer_type'])) {
            $builder->where('customer_type', $filters['customer_type']);
        }

        // Count total before pagination
        $total = (clone $builder)->countAllResults(false);

        $offset    = ($page - 1) * $perPage;
        $customers = $builder->limit($perPage, $offset)->get()->getResultArray();

        return [
            'data'  => $customers,
            'total' => $total,
            'page'  => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get customer by ID including their PICs.
     */
    public function getById(int $id): ?array
    {
        return $this->model->findWithPics($id);
    }

    public function create(array $data): array
    {
        $id = $this->model->insert($data, true);
        return $this->model->findWithPics((int) $id);
    }

    public function update(int $id, array $data): array
    {
        $this->model->update($id, $data);
        return $this->model->findWithPics($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }
}
