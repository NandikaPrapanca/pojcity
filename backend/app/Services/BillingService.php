<?php

namespace App\Services;

use App\Models\BillingItemModel;
use App\Models\BillingItemTierModel;
use App\Models\OwnershipModel;
use App\Models\IplRateModel;

class BillingService
{
    protected BillingItemModel     $model;
    protected BillingItemTierModel $tierModel;
    protected OwnershipModel       $ownershipModel;
    protected IplRateModel         $iplRateModel;

    protected array $allowedBillingTypes = ['ipl', 'water', 'electricity', 'other'];
    protected array $allowedStatuses     = ['draft', 'invoiced', 'cancelled'];

    public function __construct()
    {
        $this->model          = new BillingItemModel();
        $this->tierModel      = new BillingItemTierModel();
        $this->ownershipModel = new OwnershipModel();
        $this->iplRateModel   = new IplRateModel();
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

    // ─── IPL Billing Engine ───────────────────────────────────────────────────

    /**
     * Generate an IPL billing item from ownership configuration.
     *
     * Business logic:
     *   1. Load and validate the ownership (must exist and not be soft-deleted).
     *   2. Load the IPL rate referenced by ownership.ipl_rate_id (must exist, not deleted).
     *   3. Validate area > 0.
     *   4. Validate billing period.
     *   5. Check for duplicate (same ownership + ipl + period).
     *   6. Compute authoritative subtotal: area × rate_per_sqm (decimal-safe, rounded to 2dp).
     *   7. Insert billing_item snapshot preserving rate and area at generation time.
     *
     * @param  array $data  Keys: ownership_id, billing_period_start, billing_period_end, notes?
     * @return array        ['success' => bool, 'error' => string|null, 'data' => array|null]
     */
    public function generateIpl(array $data): array
    {
        // ── 1. Validate presence of required fields ────────────────────────────
        if (empty($data['ownership_id'])) {
            return ['success' => false, 'error' => 'Kepemilikan (ownership_id) wajib diisi.', 'data' => null];
        }
        if (empty($data['billing_period_start'])) {
            return ['success' => false, 'error' => 'Tanggal awal periode wajib diisi.', 'data' => null];
        }
        if (empty($data['billing_period_end'])) {
            return ['success' => false, 'error' => 'Tanggal akhir periode wajib diisi.', 'data' => null];
        }

        // ── 2. Validate date format ────────────────────────────────────────────
        if (!$this->isValidDate($data['billing_period_start'])) {
            return ['success' => false, 'error' => 'Format tanggal awal periode tidak valid (YYYY-MM-DD).', 'data' => null];
        }
        if (!$this->isValidDate($data['billing_period_end'])) {
            return ['success' => false, 'error' => 'Format tanggal akhir periode tidak valid (YYYY-MM-DD).', 'data' => null];
        }
        if ($data['billing_period_end'] <= $data['billing_period_start']) {
            return ['success' => false, 'error' => 'Tanggal akhir periode harus lebih besar dari tanggal awal periode.', 'data' => null];
        }

        // ── 3. Load and validate ownership ────────────────────────────────────
        $ownership = $this->ownershipModel->find((int) $data['ownership_id']);
        if (!$ownership) {
            return ['success' => false, 'error' => 'Kepemilikan yang dipilih tidak valid atau tidak ditemukan.', 'data' => null];
        }
        // Guard soft-deleted (model uses soft deletes so find() skips deleted; extra guard for safety)
        if (!empty($ownership['deleted_at'])) {
            return ['success' => false, 'error' => 'Kepemilikan yang dipilih telah dihapus dan tidak dapat digunakan.', 'data' => null];
        }

        // ── 4. Load and validate IPL rate ─────────────────────────────────────
        if (empty($ownership['ipl_rate_id'])) {
            return ['success' => false, 'error' => 'Kepemilikan ini belum memiliki tarif IPL yang dikonfigurasi.', 'data' => null];
        }

        $iplRate = $this->iplRateModel->find((int) $ownership['ipl_rate_id']);
        if (!$iplRate) {
            return ['success' => false, 'error' => 'Tarif IPL yang dikonfigurasi untuk kepemilikan ini tidak valid atau telah dihapus.', 'data' => null];
        }
        if (!empty($iplRate['deleted_at'])) {
            return ['success' => false, 'error' => 'Tarif IPL yang dikonfigurasi untuk kepemilikan ini telah dinonaktifkan.', 'data' => null];
        }

        // ── 5. Validate area ──────────────────────────────────────────────────
        $area = (float) ($ownership['area'] ?? 0);
        if ($area <= 0) {
            return ['success' => false, 'error' => 'Luas area (area) kepemilikan harus lebih besar dari 0 m² untuk menghasilkan tagihan IPL.', 'data' => null];
        }

        // ── 6. Duplicate check ────────────────────────────────────────────────
        $ownershipId   = (int) $ownership['id'];
        $periodStart   = trim($data['billing_period_start']);
        $periodEnd     = trim($data['billing_period_end']);

        if ($this->checkDuplicate($ownershipId, 'ipl', $periodStart, $periodEnd)) {
            return [
                'success' => false,
                'error'   => "Tagihan IPL untuk kepemilikan ini pada periode {$periodStart} s/d {$periodEnd} sudah ada. Tidak dapat membuat tagihan duplikat.",
                'data'    => null,
            ];
        }

        // ── 7. Authoritative backend calculation ──────────────────────────────
        $ratePerSqm = (float) $iplRate['rate_per_sqm'];
        $subtotal   = round($area * $ratePerSqm, 2);

        // ── 8. Build description ──────────────────────────────────────────────
        // Format the period for a human-readable description in Indonesian
        $startYear  = date('Y', strtotime($periodStart));
        $startMonth = $this->monthNameId(date('n', strtotime($periodStart)));
        $description = "Iuran Pengelolaan Lingkungan (IPL) {$startMonth} {$startYear}";
        if (!empty($data['description'])) {
            $description = trim($data['description']);
        }

        // ── 9. Insert billing_item snapshot ──────────────────────────────────
        $insertData = [
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'ipl',
            'billing_period_start' => $periodStart,
            'billing_period_end'   => $periodEnd,
            'description'          => $description,
            'quantity'             => $area,        // Area m² is quantity
            'unit'                 => 'm²',
            'unit_price'           => $ratePerSqm,  // Snapshot of the rate at generation time
            'subtotal'             => $subtotal,
            'apply_tax'            => 1,
            'notes'                => !empty($data['notes']) ? trim($data['notes']) : null,
            'status'               => 'draft',
        ];

        $id = $this->model->insert($insertData, true);

        return [
            'success' => true,
            'error'   => null,
            'data'    => $this->model->findWithRelations((int) $id),
        ];
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

    /**
     * Return the Indonesian month name for a given month number (1–12).
     */
    protected function monthNameId(int $month): string
    {
        $months = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];
        return $months[$month] ?? 'Bulan';
    }
}
