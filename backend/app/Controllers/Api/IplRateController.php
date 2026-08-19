<?php

namespace App\Controllers\Api;

use App\Services\PricingService;

class IplRateController extends BaseApiController
{
    protected PricingService $service;

    public function __construct()
    {
        $this->service = new PricingService();
    }

    public function index()
    {
        $filters = ['project_id' => $this->request->getGet('project_id') ?? ''];
        return $this->success($this->service->getIplRates($filters));
    }

    public function show(int $id)
    {
        $rate = $this->service->getIplRateById($id);
        if (!$rate) return $this->notFound();
        return $this->success($rate);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'project_id'    => 'required|integer',
            'name'          => 'required|max_length[100]',
            'rate_per_sqm'  => 'required|decimal|greater_than_equal_to[0]',
            'effective_date'=> 'required|valid_date',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['project_id','name','rate_per_sqm','effective_date','notes']));
        return $this->success($this->service->createIplRate($data), 'Tarif IPL berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $rate = $this->service->getIplRateById($id);
        if (!$rate) return $this->notFound();

        $body  = $this->getBody();
        $rules = [
            'name'          => 'permit_empty|max_length[100]',
            'rate_per_sqm'  => 'permit_empty|decimal|greater_than_equal_to[0]',
            'effective_date'=> 'permit_empty|valid_date',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['name','rate_per_sqm','effective_date','notes']));
        $result = $this->service->updateIplRate($id, $data);
        if (!$result) return $this->notFound();
        return $this->success($result, 'Tarif IPL berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $rate = $this->service->getIplRateById($id);
        if (!$rate) return $this->notFound();
        $this->service->deleteIplRate($id);
        return $this->success(null, 'Tarif IPL berhasil dihapus.');
    }
}
