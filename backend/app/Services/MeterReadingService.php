<?php

namespace App\Services;

use App\Models\MeterReadingModel;
use App\Models\OwnershipModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class MeterReadingService
{
    protected MeterReadingModel $model;
    protected OwnershipModel    $ownershipModel;

    protected array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    protected int   $maxSizeKb    = 5120; // 5 MB

    public function __construct()
    {
        $this->model          = new MeterReadingModel();
        $this->ownershipModel = new OwnershipModel();
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public function getAll(array $filters = []): array
    {
        return $this->model->getAllWithRelations($filters);
    }

    public function getById(int $id): ?array
    {
        return $this->model->findWithRelations($id);
    }

    public function getForOwnership(int $ownershipId): array
    {
        return $this->model->getAllWithRelations(['ownership_id' => $ownershipId]);
    }

    public function getLatest(int $ownershipId): ?array
    {
        return $this->model->getLatestForOwnership($ownershipId);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data, ?UploadedFile $photo = null): array
    {
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        // Calculate usage authoritative in backend
        $previous = (float) $data['previous_reading'];
        $current  = (float) $data['current_reading'];
        $data['usage'] = round($current - $previous, 2);

        // Handle photo upload
        if ($photo && $photo->isValid() && !$photo->hasMoved()) {
            $uploadResult = $this->handleUpload($photo);
            if (!$uploadResult['success']) {
                return ['success' => false, 'error' => $uploadResult['error'], 'data' => null];
            }
            $data['photo_path'] = $uploadResult['path'];
        }

        $id = $this->model->insert($this->sanitize($data), true);
        return ['success' => true, 'error' => null, 'data' => $this->model->findWithRelations((int) $id)];
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(int $id, array $data, ?UploadedFile $photo = null): array
    {
        $existing = $this->model->find($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Catatan meteran tidak ditemukan.', 'data' => null];
        }

        $merged = array_merge($existing, $data);

        $validation = $this->validate($merged, $id);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        // Recalculate usage
        $previous = (float) $merged['previous_reading'];
        $current  = (float) $merged['current_reading'];
        $data['usage'] = round($current - $previous, 2);

        // Handle photo upload if new one provided
        if ($photo && $photo->isValid() && !$photo->hasMoved()) {
            $uploadResult = $this->handleUpload($photo);
            if (!$uploadResult['success']) {
                return ['success' => false, 'error' => $uploadResult['error'], 'data' => null];
            }
            $data['photo_path'] = $uploadResult['path'];
        }

        $this->model->update($id, $this->sanitize($data));
        return ['success' => true, 'error' => null, 'data' => $this->model->findWithRelations($id)];
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function validate(array $data, ?int $excludeId = null): array
    {
        // ── Ownership ──────────────────────────────────────────────────────────
        if (empty($data['ownership_id'])) {
            return ['valid' => false, 'error' => 'Kepemilikan wajib dipilih.'];
        }
        $ownership = $this->ownershipModel->find((int) $data['ownership_id']);
        if (!$ownership || $ownership['deleted_at'] !== null) {
            return ['valid' => false, 'error' => 'Kepemilikan tidak ditemukan atau telah dihapus.'];
        }

        // ── Reading Date ──────────────────────────────────────────────────────
        if (empty($data['reading_date'])) {
            return ['valid' => false, 'error' => 'Tanggal pencatatan wajib diisi.'];
        }

        // ── Previous & Current Reading ────────────────────────────────────────
        if (!isset($data['previous_reading']) || !is_numeric($data['previous_reading'])) {
            return ['valid' => false, 'error' => 'Stand meter lalu wajib diisi berupa angka.'];
        }
        if (!isset($data['current_reading']) || !is_numeric($data['current_reading'])) {
            return ['valid' => false, 'error' => 'Stand meter kini wajib diisi berupa angka.'];
        }

        $prev = (float) $data['previous_reading'];
        $curr = (float) $data['current_reading'];

        if ($prev < 0) {
            return ['valid' => false, 'error' => 'Stand meter lalu tidak boleh bernilai negatif.'];
        }
        if ($curr < 0) {
            return ['valid' => false, 'error' => 'Stand meter kini tidak boleh bernilai negatif.'];
        }
        if ($curr < $prev) {
            return ['valid' => false, 'error' => 'Stand meter kini tidak boleh lebih kecil dari stand meter lalu.'];
        }

        // ── Billing Period ────────────────────────────────────────────────────
        if (empty($data['billing_period_start'])) {
            return ['valid' => false, 'error' => 'Awal periode tagihan wajib diisi.'];
        }
        if (empty($data['billing_period_end'])) {
            return ['valid' => false, 'error' => 'Akhir periode tagihan wajib diisi.'];
        }

        if (strtotime($data['billing_period_end']) < strtotime($data['billing_period_start'])) {
            return ['valid' => false, 'error' => 'Akhir periode tagihan tidak boleh lebih awal dari awal periode.'];
        }

        return ['valid' => true, 'error' => null];
    }

    // ─── Upload Handler ───────────────────────────────────────────────────────

    private function handleUpload(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, $this->allowedMimes, true)) {
            return ['success' => false, 'error' => 'Format foto tidak valid. Gunakan JPEG, PNG, atau WebP.'];
        }

        if ($file->getSizeByUnit('kb') > $this->maxSizeKb) {
            return ['success' => false, 'error' => 'Ukuran foto terlalu besar. Maksimal 5 MB.'];
        }

        $uploadPath = WRITEPATH . 'uploads/meter-photos/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $newName = $file->getRandomName();
        $file->move($uploadPath, $newName);

        return ['success' => true, 'path' => 'uploads/meter-photos/' . $newName];
    }

    // ─── Sanitize ─────────────────────────────────────────────────────────────

    private function sanitize(array $data): array
    {
        $allowed = [
            'ownership_id',
            'meter_number',
            'reading_date',
            'previous_reading',
            'current_reading',
            'usage',
            'photo_path',
            'notes',
            'billing_period_start',
            'billing_period_end',
        ];

        $clean = array_intersect_key($data, array_flip($allowed));

        if (array_key_exists('meter_number', $clean) && $clean['meter_number'] === '') {
            $clean['meter_number'] = null;
        }
        if (array_key_exists('notes', $clean) && $clean['notes'] === '') {
            $clean['notes'] = null;
        }

        return $clean;
    }
}
