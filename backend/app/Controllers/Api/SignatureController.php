<?php

namespace App\Controllers\Api;

use App\Services\SignatureService;

class SignatureController extends BaseApiController
{
    protected SignatureService $service;

    public function __construct()
    {
        $this->service = new SignatureService();
    }

    public function index()
    {
        $filters = ['company_id' => $this->request->getGet('company_id') ?? ''];
        return $this->success($this->service->getAll($filters));
    }

    public function show(int $id)
    {
        $sig = $this->service->getById($id);
        if (!$sig) return $this->notFound();
        return $this->success($sig);
    }

    public function create()
    {
        // Signatures support multipart/form-data for file upload
        $body  = $this->request->getPost() ?? [];
        $rules = [
            'company_id' => 'required|integer',
            'label'      => 'required|max_length[100]',
            'name'       => 'required|max_length[255]',
            'position'   => 'permit_empty|max_length[100]',
            'is_active'  => 'permit_empty|in_list[0,1]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['company_id','label','name','position','is_active']));
        $file = $this->request->getFile('signature_image');

        $result = $this->service->create($data, ($file && $file->isValid()) ? $file : null);
        if (!$result['success']) return $this->error($result['error'], null, 422);
        return $this->success($result['data'], 'Tanda tangan berhasil dibuat.', 201);
    }

    public function update(int $id)
    {
        $sig = $this->service->getById($id);
        if (!$sig) return $this->notFound();

        $body  = $this->request->getPost() ?? [];
        $rules = [
            'label'     => 'permit_empty|max_length[100]',
            'name'      => 'permit_empty|max_length[255]',
            'position'  => 'permit_empty|max_length[100]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = array_intersect_key($body, array_flip(['company_id','label','name','position','is_active']));
        $file = $this->request->getFile('signature_image');

        $result = $this->service->update($id, $data, ($file && $file->isValid()) ? $file : null);
        if (!$result['success']) return $this->error($result['error'], null, 422);
        return $this->success($result['data'], 'Tanda tangan berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $sig = $this->service->getById($id);
        if (!$sig) return $this->notFound();
        $this->service->delete($id);
        return $this->success(null, 'Tanda tangan berhasil dihapus.');
    }
}
