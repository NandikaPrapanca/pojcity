<?php

namespace App\Controllers\Api;

use App\Services\PricingService;

class WaterRateGroupController extends BaseApiController
{
    protected PricingService $service;

    public function __construct()
    {
        $this->service = new PricingService();
    }

    public function index()
    {
        $filters = ['project_id' => $this->request->getGet('project_id') ?? ''];
        return $this->success($this->service->getWaterRateGroups($filters));
    }

    public function show(int $id)
    {
        $group = $this->service->getWaterRateGroupById($id);
        if (!$group) return $this->notFound();
        return $this->success($group);
    }

    public function create()
    {
        $body  = $this->getBody();
        $rules = [
            'project_id' => 'required|integer',
            'name'       => 'required|max_length[100]',
            'abonemen'   => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['project_id','name','abonemen']));
        if (!isset($data['abonemen'])) $data['abonemen'] = 0;
        return $this->success($this->service->createWaterRateGroup($data), 'Grup tarif air berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $group = $this->service->getWaterRateGroupById($id);
        if (!$group) return $this->notFound();

        $body  = $this->getBody();
        $rules = [
            'name'     => 'permit_empty|max_length[100]',
            'abonemen' => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data   = array_intersect_key($body, array_flip(['name','abonemen']));
        $result = $this->service->updateWaterRateGroup($id, $data);
        if (!$result) return $this->notFound();
        return $this->success($result, 'Grup tarif air berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $group = $this->service->getWaterRateGroupById($id);
        if (!$group) return $this->notFound();
        $this->service->deleteWaterRateGroup($id);
        return $this->success(null, 'Grup tarif air berhasil dihapus.');
    }

    public function tiers(int $id)
    {
        $group = $this->service->getWaterRateGroupById($id);
        if (!$group) return $this->notFound();
        return $this->success($this->service->getTiersForGroup($id));
    }
}
