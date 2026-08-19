<?php

namespace App\Controllers\Api;

use App\Services\TaxService;

class TaxConfigurationController extends BaseApiController
{
    protected TaxService $service;

    public function __construct()
    {
        $this->service = new TaxService();
    }

    public function index()
    {
        return $this->success($this->service->getAll());
    }

    public function show(int $id)
    {
        $config = $this->service->getById($id);
        if (!$config) return $this->notFound();
        return $this->success($config);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'label'                       => 'required|max_length[100]',
            'dpp_multiplier_numerator'    => 'required|integer|greater_than[0]',
            'dpp_multiplier_denominator'  => 'required|integer|greater_than[0]',
            'ppn_rate'                    => 'required|decimal|greater_than_equal_to[0]',
            'effective_date'              => 'required|valid_date',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['label','dpp_multiplier_numerator','dpp_multiplier_denominator','ppn_rate','effective_date'];
        $data   = array_intersect_key($body, array_flip($fields));
        $data['is_active'] = 0; // New configs start inactive
        return $this->success($this->service->create($data), 'Konfigurasi pajak berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $config = $this->service->getById($id);
        if (!$config) return $this->notFound();

        $body  = $this->getBody();
        $rules = [
            'label'                       => 'permit_empty|max_length[100]',
            'dpp_multiplier_numerator'    => 'permit_empty|integer|greater_than[0]',
            'dpp_multiplier_denominator'  => 'permit_empty|integer|greater_than[0]',
            'ppn_rate'                    => 'permit_empty|decimal|greater_than_equal_to[0]',
            'effective_date'              => 'permit_empty|valid_date',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $fields = ['label','dpp_multiplier_numerator','dpp_multiplier_denominator','ppn_rate','effective_date'];
        $data   = array_intersect_key($body, array_flip($fields));
        $result = $this->service->update($id, $data);
        if (!$result) return $this->notFound();
        return $this->success($result, 'Konfigurasi pajak berhasil diperbarui.');
    }

    public function activate(int $id)
    {
        $result = $this->service->activate($id);
        if (!$result['success']) {
            return $this->error($result['error'], null, 404);
        }
        return $this->success($result['data'], 'Konfigurasi pajak berhasil diaktifkan.');
    }
}
