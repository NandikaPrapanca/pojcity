<?php

namespace App\Services;

use App\Models\PicModel;
use App\Models\CustomerModel;

class PicService
{
    protected PicModel $model;
    protected CustomerModel $customerModel;

    public function __construct()
    {
        $this->model         = new PicModel();
        $this->customerModel = new CustomerModel();
    }

    public function getByCustomer(int $customerId): array
    {
        return $this->model
            ->where('customer_id', $customerId)
            ->where('deleted_at IS NULL')
            ->findAll();
    }

    public function create(int $customerId, array $data): array
    {
        $data['customer_id'] = $customerId;
        $id = $this->model->insert($data, true);
        return $this->model->find($id);
    }

    public function update(int $customerId, int $id, array $data): ?array
    {
        $pic = $this->model->where('id', $id)
                           ->where('customer_id', $customerId)
                           ->where('deleted_at IS NULL')
                           ->first();
        if (!$pic) return null;

        $this->model->update($id, $data);
        return $this->model->find($id);
    }

    public function delete(int $customerId, int $id): bool
    {
        $pic = $this->model->where('id', $id)
                           ->where('customer_id', $customerId)
                           ->where('deleted_at IS NULL')
                           ->first();
        if (!$pic) return false;

        return (bool) $this->model->delete($id);
    }
}
