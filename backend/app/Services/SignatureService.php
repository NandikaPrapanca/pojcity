<?php

namespace App\Services;

use App\Models\SignatureModel;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\Files\UploadedFile;

class SignatureService
{
    protected SignatureModel $model;

    protected array $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    protected int   $maxSizeKb    = 2048; // 2 MB

    public function __construct()
    {
        $this->model = new SignatureModel();
    }

    public function getAll(array $filters = []): array
    {
        $builder = $this->model->builder();
        $builder->where('signatures.deleted_at IS NULL');

        if (!empty($filters['company_id'])) {
            $builder->where('company_id', (int) $filters['company_id']);
        }

        return $builder->orderBy('label', 'ASC')->get()->getResultArray();
    }

    public function getById(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function create(array $data, ?UploadedFile $file = null): array
    {
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $uploadResult = $this->handleUpload($file);
            if (!$uploadResult['success']) {
                return ['success' => false, 'error' => $uploadResult['error'], 'data' => null];
            }
            $data['signature_path'] = $uploadResult['path'];
        }

        $id = $this->model->insert($data, true);
        return ['success' => true, 'error' => null, 'data' => $this->model->find($id)];
    }

    public function update(int $id, array $data, ?UploadedFile $file = null): array
    {
        $signature = $this->model->find($id);
        if (!$signature) {
            return ['success' => false, 'error' => 'Tanda tangan tidak ditemukan.', 'data' => null];
        }

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $uploadResult = $this->handleUpload($file);
            if (!$uploadResult['success']) {
                return ['success' => false, 'error' => $uploadResult['error'], 'data' => null];
            }
            $data['signature_path'] = $uploadResult['path'];
        }

        if (!empty($data)) {
            $this->model->update($id, $data);
        }
        return ['success' => true, 'error' => null, 'data' => $this->model->find($id)];
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    private function handleUpload(UploadedFile $file): array
    {
        if (!in_array($file->getMimeType(), $this->allowedMimes)) {
            return ['success' => false, 'error' => 'Format file tidak valid. Gunakan JPG, PNG, GIF, atau WebP.'];
        }

        if ($file->getSizeByUnit('kb') > $this->maxSizeKb) {
            return ['success' => false, 'error' => 'Ukuran file terlalu besar. Maksimal 2 MB.'];
        }

        $uploadPath = WRITEPATH . 'uploads/signatures/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $newName = $file->getRandomName();
        $file->move($uploadPath, $newName);

        return ['success' => true, 'path' => 'uploads/signatures/' . $newName];
    }
}
