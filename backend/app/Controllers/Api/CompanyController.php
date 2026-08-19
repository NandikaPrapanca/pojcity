<?php

namespace App\Controllers\Api;

use App\Services\CompanyService;

class CompanyController extends BaseApiController
{
    protected CompanyService $service;

    public function __construct()
    {
        $this->service = new CompanyService();
    }

    public function index()
    {
        return $this->success($this->service->getAll(), 'OK');
    }

    public function show(int $id)
    {
        $company = $this->service->getById($id);
        if (!$company) return $this->notFound();
        return $this->success($company);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'name'  => 'required|max_length[255]',
            'email' => 'permit_empty|valid_email',
            'phone' => 'permit_empty|max_length[50]',
            'npwp'  => 'permit_empty|max_length[50]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['name','address','phone','email','npwp']));
        return $this->success($this->service->create($data), 'Perusahaan berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $company = $this->service->getById($id);
        if (!$company) return $this->notFound();

        $body  = $this->getBody();
        $rules = [
            'name'  => 'permit_empty|max_length[255]',
            'email' => 'permit_empty|valid_email',
            'phone' => 'permit_empty|max_length[50]',
            'npwp'  => 'permit_empty|max_length[50]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['name','address','phone','email','npwp','logo_path']));
        return $this->success($this->service->update($id, $data), 'Perusahaan berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $company = $this->service->getById($id);
        if (!$company) return $this->notFound();

        $this->service->delete($id);
        return $this->success(null, 'Perusahaan berhasil dihapus.');
    }
}
