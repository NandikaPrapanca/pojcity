<?php

namespace App\Services;

use App\Models\BillingItemModel;
use App\Models\BillingItemTierModel;
use App\Models\OwnershipModel;

class BillingService
{
    protected BillingItemModel     $model;
    protected BillingItemTierModel $tierModel;
    protected OwnershipModel       $ownershipModel;

    protected array $allowedBillingTypes = ['ipl', 'water', 'electricity', 'other'];
    protected array $allowedStatuses     = ['draft', 'invoiced', 'cancelled'];

    public function __construct()
    {
        $this->model          = new BillingItemModel();
        $this->tierModel      = new BillingItemTierModel();
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

    public function getByOwnership(int $ownershipId): array
    {
        return $this->model->getAllWithRelations(['ownership_id' => $ownershipId]);
    }

    public function getByPeriod(string $startDate, string $endDate): array
    {
        return $this->model->getAllWithRelations([
            'billing_period_start' => $startDate,
            'billing_period_end'   => $endDate,
        ]);
    }

    // ─── Duplicate Check ──────────────────────────────────────────────────────

    /**
     * Duplicate billing check logic:
     * Returns true if an active item already exists for the same ownership, billing_type, and billing period range.
     */
    public function checkDuplicate(int $ownershipId, string $billingType, string $periodStart, string $periodEnd, ?int $excludeId = null): bool
    {
        $duplicate = $this->model->findDuplicate($ownershipId, $billingType, $periodStart, $periodEnd, $excludeId);
        return $duplicate !== null;
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): array
    {
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        $sanitized = $this->sanitize($data);

        // Duplicate prevention check
        if ($this->checkDuplicate(
            (int) $sanitized['ownership_id'],
            $sanitized['billing_type'],
            $sanitized['billing_period_start'],
            $sanitized['billing_period_end']
        )) {
            $typeLabel = strtoupper($sanitized['billing_type']);
            return [
                'success' => false,
                'error'   => "Item tagihan untuk kepemilikan, jenis tagihan ({$typeLabel}), dan periode {$sanitized['billing_period_start']} s/d {$sanitized['billing_period_end']} sudah ada.",
                'data'    => null,
            ];
        }

        // Authoritative backend decimal calculation for subtotal
        $qty       = (float) ($sanitized['quantity'] ?? 1);
        $unitPrice = (float) ($sanitized['unit_price'] ?? 0);
        $sanitized['subtotal'] = round($qty * $unitPrice, 2);

        $id = $this->model->insert($sanitized, true);

        // Optional: insert tiers if provided
        if (!empty($data['tiers']) && is_array($data['tiers'])) {
            foreach ($data['tiers'] as $tier) {
                $this->tierModel->insert([
                    'billing_item_id' => (int) $id,
                    'tier_label'      => $tier['tier_label'] ?? '',
                    'usage_in_tier'   => (float) ($tier['usage_in_tier'] ?? 0),
                    'rate'            => (float) ($tier['rate'] ?? 0),
                    'amount'          => round((float) ($tier['usage_in_tier'] ?? 0) * (float) ($tier['rate'] ?? 0), 2),
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return ['success' => true, 'error' => null, 'data' => $this->model->findWithRelations((int) $id)];
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(int $id, array $data): array
    {
        $existing = $this->model->find($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Item tagihan tidak ditemukan.', 'data' => null];
        }

        if ($existing['status'] === 'invoiced') {
            return ['success' => false, 'error' => 'Item tagihan yang sudah masuk invoice tidak dapat diubah.', 'data' => null];
        }

        $merged = array_merge($existing, $data);

        $validation = $this->validate($merged, $id);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        $sanitized = $this->sanitize($merged);

        // Duplicate prevention check excluding current id
        if ($this->checkDuplicate(
            (int) $sanitized['ownership_id'],
            $sanitized['billing_type'],
            $sanitized['billing_period_start'],
            $sanitized['billing_period_end'],
            $id
        )) {
            $typeLabel = strtoupper($sanitized['billing_type']);
            return [
                'success' => false,
                'error'   => "Item tagihan untuk kepemilikan, jenis tagihan ({$typeLabel}), dan periode {$sanitized['billing_period_start']} s/d {$sanitized['billing_period_end']} sudah ada.",
                'data'    => null,
            ];
        }

        // Authoritative backend decimal calculation for subtotal
        $qty       = (float) ($sanitized['quantity'] ?? 1);
        $unitPrice = (float) ($sanitized['unit_price'] ?? 0);
        $sanitized['subtotal'] = round($qty * $unitPrice, 2);

        $this->model->update($id, $sanitized);

        // Update tiers if provided
        if (isset($data['tiers']) && is_array($data['tiers'])) {
            $this->tierModel->where('billing_item_id', $id)->delete();
            foreach ($data['tiers'] as $tier) {
                $this->tierModel->insert([
                    'billing_item_id' => $id,
                    'tier_label'      => $tier['tier_label'] ?? '',
                    'usage_in_tier'   => (float) ($tier['usage_in_tier'] ?? 0),
                    'rate'            => (float) ($tier['rate'] ?? 0),
                    'amount'          => round((float) ($tier['usage_in_tier'] ?? 0) * (float) ($tier['rate'] ?? 0), 2),
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return ['success' => true, 'error' => null, 'data' => $this->model->findWithRelations($id)];
    }

    // ─── Delete / Cancel ──────────────────────────────────────────────────────

    public function delete(int $id): array
    {
        $existing = $this->model->find($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Item tagihan tidak ditemukan.', 'data' => null];
        }

        if ($existing['status'] === 'invoiced') {
            return ['success' => false, 'error' => 'Item tagihan yang sudah masuk invoice tidak dapat dihapus.', 'data' => null];
        }

        // Perform soft delete to preserve historical records
        $this->model->delete($id);

        return ['success' => true, 'error' => null, 'data' => null];
    }

    // ─── Validation & Sanitization ────────────────────────────────────────────

    protected function validate(array $data, ?int $id = null): array
    {
        // 1. Validate ownership
        if (empty($data['ownership_id'])) {
            return ['valid' => false, 'error' => 'Kepemilikan (ownership) wajib dipilih.'];
        }

        $ownership = $this->ownershipModel->find((int) $data['ownership_id']);
        if (!$ownership) {
            return ['valid' => false, 'error' => 'Kepemilikan yang dipilih tidak valid atau tidak ditemukan.'];
        }

        // 2. Validate billing type
        if (empty($data['billing_type'])) {
            return ['valid' => false, 'error' => 'Jenis tagihan wajib dipilih.'];
        }

        $type = strtolower(trim((string) $data['billing_type']));
        if (!in_array($type, $this->allowedBillingTypes, true)) {
            return ['valid' => false, 'error' => 'Jenis tagihan tidak valid. Pilih: IPL, Water, Electricity, atau Other.'];
        }

        // 3. Validate billing periods
        if (empty($data['billing_period_start'])) {
            return ['valid' => false, 'error' => 'Tanggal awal periode tagihan wajib diisi.'];
        }

        if (empty($data['billing_period_end'])) {
            return ['valid' => false, 'error' => 'Tanggal akhir periode tagihan wajib diisi.'];
        }

        if (!$this->isValidDate($data['billing_period_start'])) {
            return ['valid' => false, 'error' => 'Format tanggal awal periode tidak valid (YYYY-MM-DD).'];
        }

        if (!$this->isValidDate($data['billing_period_end'])) {
            return ['valid' => false, 'error' => 'Format tanggal akhir periode tidak valid (YYYY-MM-DD).'];
        }

        if ($data['billing_period_end'] <= $data['billing_period_start']) {
            return ['valid' => false, 'error' => 'Tanggal akhir periode harus lebih besar dari tanggal awal periode.'];
        }

        // 4. Validate description
        if (empty(trim((string) ($data['description'] ?? '')))) {
            return ['valid' => false, 'error' => 'Deskripsi item tagihan wajib diisi.'];
        }

        // 5. Validate numeric quantities and unit price
        if (isset($data['quantity']) && (!is_numeric($data['quantity']) || (float) $data['quantity'] < 0)) {
            return ['valid' => false, 'error' => 'Kuantitas harus berupa angka positif.'];
        }

        if (isset($data['unit_price']) && (!is_numeric($data['unit_price']) || (float) $data['unit_price'] < 0)) {
            return ['valid' => false, 'error' => 'Harga satuan harus berupa angka non-negatif.'];
        }

        return ['valid' => true, 'error' => null];
    }

    protected function sanitize(array $data): array
    {
        $billingType = strtolower(trim((string) ($data['billing_type'] ?? 'ipl')));
        $status      = strtolower(trim((string) ($data['status'] ?? 'draft')));

        if (!in_array($status, $this->allowedStatuses, true)) {
            $status = 'draft';
        }

        return [
            'ownership_id'          => (int) $data['ownership_id'],
            'billing_type'          => $billingType,
            'billing_period_start'  => trim((string) $data['billing_period_start']),
            'billing_period_end'    => trim((string) $data['billing_period_end']),
            'meter_reading_id'      => !empty($data['meter_reading_id']) ? (int) $data['meter_reading_id'] : null,
            'description'           => trim((string) $data['description']),
            'quantity'              => isset($data['quantity']) ? (float) $data['quantity'] : 1.00,
            'unit'                  => trim((string) ($data['unit'] ?? 'ls')),
            'unit_price'            => isset($data['unit_price']) ? (float) $data['unit_price'] : 0.00,
            'subtotal'              => isset($data['subtotal']) ? (float) $data['subtotal'] : 0.00,
            'management_fee_rate'   => isset($data['management_fee_rate']) && is_numeric($data['management_fee_rate']) ? (float) $data['management_fee_rate'] : null,
            'management_fee_amount' => isset($data['management_fee_amount']) && is_numeric($data['management_fee_amount']) ? (float) $data['management_fee_amount'] : null,
            'pln_charge'            => isset($data['pln_charge']) && is_numeric($data['pln_charge']) ? (float) $data['pln_charge'] : null,
            'apply_tax'             => isset($data['apply_tax']) ? (int) (bool) $data['apply_tax'] : 1,
            'notes'                 => !empty($data['notes']) ? trim((string) $data['notes']) : null,
            'status'                => $status,
        ];
    }

    protected function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
