<?php

namespace App\Controllers\Api;

use App\Services\PricingService;

class WaterRateTierController extends BaseApiController
{
    protected PricingService $service;

    public function __construct()
    {
        $this->service = new PricingService();
    }

    public function createForGroup(int $groupId)
    {
        $body  = $this->getBody();
        $rules = [
            'min_usage'   => 'required|decimal|greater_than_equal_to[0]',
            'max_usage'   => 'permit_empty|decimal',
            'rate_per_m3' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['min_usage','max_usage','rate_per_m3']));
        // Normalise empty max_usage to null
        if (isset($data['max_usage']) && $data['max_usage'] === '') {
            $data['max_usage'] = null;
        }

        $result = $this->service->createTier($groupId, $data);
        if (!$result['success']) return $this->error($result['error'], null, 422);
        return $this->success($result['data'], 'Tier tarif air berhasil ditambahkan.', 201);
    }

    public function update(int $id)
    {
        $body  = $this->getBody();
        $rules = [
            'min_usage'   => 'permit_empty|decimal|greater_than_equal_to[0]',
            'max_usage'   => 'permit_empty|decimal',
            'rate_per_m3' => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['min_usage','max_usage','rate_per_m3']));
        if (array_key_exists('max_usage', $data) && $data['max_usage'] === '') {
            $data['max_usage'] = null;
        }

        $result = $this->service->updateTier($id, $data);
        if (!$result['success']) return $this->error($result['error'], null, 422);
        return $this->success($result['data'], 'Tier tarif air berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $deleted = $this->service->deleteTier($id);
        if (!$deleted) return $this->notFound();
        return $this->success(null, 'Tier tarif air berhasil dihapus.');
    }
}
