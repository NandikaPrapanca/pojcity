<?php

namespace App\Services;

use App\Models\OwnershipModel;
use App\Models\CustomerModel;
use App\Models\ProjectModel;
use App\Models\ClusterModel;
use App\Models\BlockModel;
use App\Models\LotModel;
use App\Models\IplRateModel;
use App\Models\WaterRateGroupModel;

class OwnershipService
{
    protected OwnershipModel      $model;
    protected CustomerModel       $customerModel;
    protected ProjectModel        $projectModel;
    protected ClusterModel        $clusterModel;
    protected BlockModel          $blockModel;
    protected LotModel            $lotModel;
    protected IplRateModel        $iplRateModel;
    protected WaterRateGroupModel $waterGroupModel;

    public function __construct()
    {
        $this->model           = new OwnershipModel();
        $this->customerModel   = new CustomerModel();
        $this->projectModel    = new ProjectModel();
        $this->clusterModel    = new ClusterModel();
        $this->blockModel      = new BlockModel();
        $this->lotModel        = new LotModel();
        $this->iplRateModel    = new IplRateModel();
        $this->waterGroupModel = new WaterRateGroupModel();
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

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): array
    {
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        // Duplicate check
        $lotId = isset($data['lot_id']) ? (int) $data['lot_id'] : null;
        $dup = $this->model->findDuplicate((int) $data['customer_id'], (int) $data['project_id'], $lotId);
        if ($dup) {
            return [
                'success' => false,
                'error'   => 'Kepemilikan aktif untuk customer dan properti yang sama sudah ada. Harap nonaktifkan kepemilikan yang lama terlebih dahulu.',
                'data'    => null,
            ];
        }

        $id = $this->model->insert($this->sanitize($data), true);
        return ['success' => true, 'error' => null, 'data' => $this->model->findWithRelations((int) $id)];
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(int $id, array $data): array
    {
        $ownership = $this->model->find($id);
        if (!$ownership) {
            return ['success' => false, 'error' => 'Kepemilikan tidak ditemukan.', 'data' => null];
        }

        // Merge with existing values for validation
        $merged = array_merge($ownership, $data);

        $validation = $this->validate($merged);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        // Duplicate check (exclude self)
        $lotId = isset($merged['lot_id']) ? (int) $merged['lot_id'] : null;
        $dup = $this->model->findDuplicate((int) $merged['customer_id'], (int) $merged['project_id'], $lotId, $id);
        if ($dup) {
            return [
                'success' => false,
                'error'   => 'Kepemilikan aktif untuk customer dan properti yang sama sudah ada.',
                'data'    => null,
            ];
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

    /**
     * Full business validation according to ARCHITECTURE.md rules.
     * Returns ['valid' => bool, 'error' => string|null]
     */
    public function validate(array $data): array
    {
        $type = $data['ownership_type'] ?? '';

        // ── Customer ──────────────────────────────────────────────────────────
        if (empty($data['customer_id'])) {
            return ['valid' => false, 'error' => 'Customer wajib diisi.'];
        }
        $customer = $this->customerModel->find((int) $data['customer_id']);
        if (!$customer || $customer['deleted_at'] !== null) {
            return ['valid' => false, 'error' => 'Customer tidak ditemukan atau telah dihapus.'];
        }

        // ── Project ───────────────────────────────────────────────────────────
        if (empty($data['project_id'])) {
            return ['valid' => false, 'error' => 'Proyek wajib diisi.'];
        }
        $project = $this->projectModel->find((int) $data['project_id']);
        if (!$project || $project['deleted_at'] !== null) {
            return ['valid' => false, 'error' => 'Proyek tidak ditemukan atau telah dihapus.'];
        }

        // ── Ownership type must match project type ─────────────────────────────
        if (empty($type)) {
            return ['valid' => false, 'error' => 'Tipe kepemilikan wajib diisi.'];
        }
        if (!in_array($type, ['residential', 'commercial'], true)) {
            return ['valid' => false, 'error' => 'Tipe kepemilikan tidak valid.'];
        }
        if ($project['project_type'] !== $type) {
            return [
                'valid' => false,
                'error' => "Tipe kepemilikan ({$type}) tidak sesuai dengan tipe proyek ({$project['project_type']}).",
            ];
        }

        // ── Residential hierarchy ─────────────────────────────────────────────
        if ($type === 'residential') {
            if (empty($data['cluster_id'])) {
                return ['valid' => false, 'error' => 'Cluster wajib diisi untuk kepemilikan residensial.'];
            }
            if (empty($data['block_id'])) {
                return ['valid' => false, 'error' => 'Blok wajib diisi untuk kepemilikan residensial.'];
            }
            if (empty($data['lot_id'])) {
                return ['valid' => false, 'error' => 'Kavling wajib diisi untuk kepemilikan residensial.'];
            }

            // Cluster belongs to project
            $cluster = $this->clusterModel->find((int) $data['cluster_id']);
            if (!$cluster || $cluster['deleted_at'] !== null) {
                return ['valid' => false, 'error' => 'Cluster tidak ditemukan atau telah dihapus.'];
            }
            if ((int) $cluster['project_id'] !== (int) $data['project_id']) {
                return ['valid' => false, 'error' => 'Cluster tidak termasuk dalam proyek yang dipilih.'];
            }

            // Block belongs to cluster
            $block = $this->blockModel->find((int) $data['block_id']);
            if (!$block || $block['deleted_at'] !== null) {
                return ['valid' => false, 'error' => 'Blok tidak ditemukan atau telah dihapus.'];
            }
            if ((int) $block['cluster_id'] !== (int) $data['cluster_id']) {
                return ['valid' => false, 'error' => 'Blok tidak termasuk dalam cluster yang dipilih.'];
            }

            // Lot belongs to block
            $lot = $this->lotModel->find((int) $data['lot_id']);
            if (!$lot || $lot['deleted_at'] !== null) {
                return ['valid' => false, 'error' => 'Kavling tidak ditemukan atau telah dihapus.'];
            }
            if ((int) $lot['block_id'] !== (int) $data['block_id']) {
                return ['valid' => false, 'error' => 'Kavling tidak termasuk dalam blok yang dipilih.'];
            }
        }

        // ── Commercial must NOT have hierarchy fields ──────────────────────────
        if ($type === 'commercial') {
            if (!empty($data['cluster_id'])) {
                return ['valid' => false, 'error' => 'Proyek komersial tidak boleh memiliki cluster.'];
            }
            if (!empty($data['block_id'])) {
                return ['valid' => false, 'error' => 'Proyek komersial tidak boleh memiliki blok.'];
            }
            if (!empty($data['lot_id'])) {
                return ['valid' => false, 'error' => 'Proyek komersial tidak boleh memiliki kavling.'];
            }
        }

        // ── Area ──────────────────────────────────────────────────────────────
        if (!isset($data['area']) || (float) $data['area'] <= 0) {
            return ['valid' => false, 'error' => 'Luas area harus lebih dari 0.'];
        }

        // ── IPL Rate ──────────────────────────────────────────────────────────
        if (empty($data['ipl_rate_id'])) {
            return ['valid' => false, 'error' => 'Tarif IPL wajib diisi.'];
        }
        $iplRate = $this->iplRateModel->find((int) $data['ipl_rate_id']);
        if (!$iplRate || $iplRate['deleted_at'] !== null) {
            return ['valid' => false, 'error' => 'Tarif IPL tidak ditemukan atau telah dihapus.'];
        }
        if ((int) $iplRate['project_id'] !== (int) $data['project_id']) {
            return ['valid' => false, 'error' => 'Tarif IPL tidak termasuk dalam proyek yang dipilih.'];
        }

        // ── Water Rate Group (optional) ───────────────────────────────────────
        if (!empty($data['water_rate_group_id'])) {
            $wg = $this->waterGroupModel->find((int) $data['water_rate_group_id']);
            if (!$wg || $wg['deleted_at'] !== null) {
                return ['valid' => false, 'error' => 'Paket tarif air tidak ditemukan atau telah dihapus.'];
            }
            if ((int) $wg['project_id'] !== (int) $data['project_id']) {
                return ['valid' => false, 'error' => 'Paket tarif air tidak termasuk dalam proyek yang dipilih.'];
            }
        }

        // ── Start date ────────────────────────────────────────────────────────
        if (empty($data['start_date'])) {
            return ['valid' => false, 'error' => 'Tanggal mulai wajib diisi.'];
        }

        // ── End date (optional, but must not be before start) ─────────────────
        if (!empty($data['end_date'])) {
            if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
                return ['valid' => false, 'error' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    // ─── Sanitize ─────────────────────────────────────────────────────────────

    private function sanitize(array $data): array
    {
        $allowed = [
            'customer_id', 'project_id', 'cluster_id', 'block_id', 'lot_id',
            'billing_address', 'area', 'ipl_rate_id', 'water_rate_group_id',
            'ownership_type', 'start_date', 'end_date', 'notes',
        ];
        $clean = array_intersect_key($data, array_flip($allowed));

        // Normalise nullable FK fields
        foreach (['cluster_id', 'block_id', 'lot_id', 'water_rate_group_id', 'end_date'] as $nullable) {
            if (array_key_exists($nullable, $clean) && ($clean[$nullable] === '' || $clean[$nullable] === '0')) {
                $clean[$nullable] = null;
            }
        }

        return $clean;
    }
}
