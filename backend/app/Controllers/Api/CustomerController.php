<?php

namespace App\Controllers\Api;

use App\Services\CustomerService;

class CustomerController extends BaseApiController
{
    protected CustomerService $service;

    public function __construct()
    {
        $this->service = new CustomerService();
    }

    public function index()
    {
        $filters = [
            'search'        => $this->request->getGet('search') ?? '',
            'customer_type' => $this->request->getGet('customer_type') ?? '',
            'page'          => $this->request->getGet('page') ?? 1,
            'per_page'      => $this->request->getGet('per_page') ?? 20,
        ];
        return $this->success($this->service->getAll($filters));
    }

    public function show(int $id)
    {
        $customer = $this->service->getById($id);
        if (!$customer) return $this->notFound();
        return $this->success($customer);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'name'          => 'required|max_length[255]',
            'customer_type' => 'required|in_list[individual,cv,pt,institution]',
            'email'         => 'permit_empty|valid_email',
            'whatsapp'      => 'permit_empty|max_length[50]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        // Attach first company as default if not provided
        if (empty($body['company_id'])) {
            $company = (new \App\Models\CompanyModel())->first();
            $body['company_id'] = $company['id'] ?? 1;
        }

        $fields = ['company_id','name','customer_type','nik','npwp','whatsapp','email','billing_address','notes'];
        $data   = array_intersect_key($body, array_flip($fields));
        return $this->success($this->service->create($data), 'Customer berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $customer = $this->service->getById($id);
        if (!$customer) return $this->notFound();

        $body  = $this->getBody();
        $rules = [
            'name'          => 'permit_empty|max_length[255]',
            'customer_type' => 'permit_empty|in_list[individual,cv,pt,institution]',
            'email'         => 'permit_empty|valid_email',
            'whatsapp'      => 'permit_empty|max_length[50]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['company_id','name','customer_type','nik','npwp','whatsapp','email','billing_address','notes'];
        $data   = array_intersect_key($body, array_flip($fields));
        return $this->success($this->service->update($id, $data), 'Customer berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $customer = $this->service->getById($id);
        if (!$customer) return $this->notFound();

        $this->service->delete($id);
        return $this->success(null, 'Customer berhasil dihapus.');
    }
}
