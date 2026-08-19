<?php

namespace App\Controllers\Api;

use App\Services\PicService;

class PicController extends BaseApiController
{
    protected PicService $service;

    public function __construct()
    {
        $this->service = new PicService();
    }

    public function index(int $customerId)
    {
        return $this->success($this->service->getByCustomer($customerId));
    }

    public function create(int $customerId)
    {
        $body  = $this->getBody();
        $rules = [
            'name'       => 'required|max_length[255]',
            'phone'      => 'permit_empty|max_length[50]',
            'email'      => 'permit_empty|valid_email',
            'is_primary' => 'permit_empty|in_list[0,1]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['name','phone','email','position','is_primary'];
        $data   = array_intersect_key($body, array_flip($fields));
        return $this->success($this->service->create($customerId, $data), 'PIC berhasil ditambahkan.', 201);
    }

    public function update(int $customerId, int $id)
    {
        $body  = $this->getBody();
        $rules = [
            'name'       => 'permit_empty|max_length[255]',
            'phone'      => 'permit_empty|max_length[50]',
            'email'      => 'permit_empty|valid_email',
            'is_primary' => 'permit_empty|in_list[0,1]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['name','phone','email','position','is_primary'];
        $data   = array_intersect_key($body, array_flip($fields));
        $result = $this->service->update($customerId, $id, $data);

        if ($result === null) return $this->notFound('PIC tidak ditemukan.');
        return $this->success($result, 'PIC berhasil diperbarui.');
    }

    public function delete(int $customerId, int $id)
    {
        $deleted = $this->service->delete($customerId, $id);
        if (!$deleted) return $this->notFound('PIC tidak ditemukan.');
        return $this->success(null, 'PIC berhasil dihapus.');
    }
}
