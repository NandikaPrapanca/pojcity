<?php

namespace App\Services;

use App\Models\BillingItemModel;
use App\Models\BillingItemTierModel;
use App\Models\OwnershipModel;
use App\Models\IplRateModel;
use App\Models\WaterRateGroupModel;
use App\Models\WaterRateTierModel;
use App\Models\MeterReadingModel;

class BillingService
{
    protected BillingItemModel     $model;
    protected BillingItemTierModel $tierModel;
    protected OwnershipModel       $ownershipModel;
    protected IplRateModel         $iplRateModel;
    protected WaterRateGroupModel  $waterRateGroupModel;
    protected WaterRateTierModel   $waterRateTierModel;
    protected MeterReadingModel    $meterReadingModel;

    protected array $allowedBillingTypes = ['ipl', 'water', 'electricity', 'other'];
    protected array $allowedStatuses     = ['draft', 'invoiced', 'cancelled'];

    public function __construct()
    {
        $this->model               = new BillingItemModel();
        $this->tierModel           = new BillingItemTierModel();
        $this->ownershipModel      = new OwnershipModel();
        $this->iplRateModel        = new IplRateModel();
        $this->waterRateGroupModel = new WaterRateGroupModel();
        $this->waterRateTierModel  = new WaterRateTierModel();
        $this->meterReadingModel   = new MeterReadingModel();
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

    // ─── Water Billing Engine ─────────────────────────────────────────────────

    /**
     * Generate a Water billing item from ownership + latest meter reading + water rate group.
     *
     * Business logic:
     *   1. Validate required fields and date format.
     *   2. Load and validate ownership (must exist, not soft-deleted).
     *   3. Validate ownership has an active water_rate_group_id.
     *   4. Load latest meter reading for ownership (must exist, not soft-deleted).
     *   5. Validate usage: current_reading >= previous_reading.
     *   6. Load water rate tiers (ordered by min_usage ASC).
     *   7. Run progressive tier calculation.
     *   8. Add abonemen from the water rate group.
     *   9. Duplicate check (same ownership + water + period).
     *  10. Insert billing_item snapshot + billing_item_tiers records.
     *
     * @param  array $data  Keys: ownership_id, billing_period_start, billing_period_end
     * @return array        ['success' => bool, 'error' => string|null, 'data' => array|null]
     */
    public function generateWater(array $data): array
    {
        // ── 1. Validate required fields ───────────────────────────────────────
        if (empty($data['ownership_id'])) {
            return ['success' => false, 'error' => 'Kepemilikan (ownership_id) wajib diisi.', 'data' => null];
        }
        if (empty($data['billing_period_start'])) {
            return ['success' => false, 'error' => 'Tanggal awal periode wajib diisi.', 'data' => null];
        }
        if (empty($data['billing_period_end'])) {
            return ['success' => false, 'error' => 'Tanggal akhir periode wajib diisi.', 'data' => null];
        }
        if (!$this->isValidDate($data['billing_period_start'])) {
            return ['success' => false, 'error' => 'Format tanggal awal periode tidak valid (YYYY-MM-DD).', 'data' => null];
        }
        if (!$this->isValidDate($data['billing_period_end'])) {
            return ['success' => false, 'error' => 'Format tanggal akhir periode tidak valid (YYYY-MM-DD).', 'data' => null];
        }
        if ($data['billing_period_end'] <= $data['billing_period_start']) {
            return ['success' => false, 'error' => 'Tanggal akhir periode harus lebih besar dari tanggal awal periode.', 'data' => null];
        }

        // ── 2. Load and validate ownership ────────────────────────────────────
        $ownership = $this->ownershipModel->find((int) $data['ownership_id']);
        if (!$ownership) {
            return ['success' => false, 'error' => 'Kepemilikan yang dipilih tidak valid atau tidak ditemukan.', 'data' => null];
        }
        if (!empty($ownership['deleted_at'])) {
            return ['success' => false, 'error' => 'Kepemilikan yang dipilih telah dihapus dan tidak dapat digunakan.', 'data' => null];
        }

        $ownershipId = (int) $ownership['id'];
        $periodStart = trim($data['billing_period_start']);
        $periodEnd   = trim($data['billing_period_end']);

        // ── 3. Validate water rate group ──────────────────────────────────────
        if (empty($ownership['water_rate_group_id'])) {
            return ['success' => false, 'error' => 'Kepemilikan ini belum memiliki Kelompok Tarif Air yang dikonfigurasi.', 'data' => null];
        }

        $waterGroup = $this->waterRateGroupModel->find((int) $ownership['water_rate_group_id']);
        if (!$waterGroup) {
            return ['success' => false, 'error' => 'Kelompok Tarif Air yang dikonfigurasi tidak valid atau telah dihapus.', 'data' => null];
        }
        if (!empty($waterGroup['deleted_at'])) {
            return ['success' => false, 'error' => 'Kelompok Tarif Air yang dikonfigurasi telah dinonaktifkan.', 'data' => null];
        }

        // ── 4. Load latest meter reading ──────────────────────────────────────
        $reading = $this->meterReadingModel->getLatestForOwnership($ownershipId);
        if (!$reading) {
            return ['success' => false, 'error' => 'Tidak ditemukan data meter reading yang valid untuk kepemilikan ini. Pastikan data meter sudah diinput terlebih dahulu.', 'data' => null];
        }

        // ── 5. Validate usage (current_reading >= previous_reading) ───────────
        $currentReading  = (float) $reading['current_reading'];
        $previousReading = (float) $reading['previous_reading'];
        $usage           = round($currentReading - $previousReading, 2);

        if ($currentReading < $previousReading) {
            return [
                'success' => false,
                'error'   => "Data meter reading tidak valid: angka current ({$currentReading}) lebih kecil dari previous ({$previousReading}). Tagihan air tidak dapat digenerate.",
                'data'    => null,
            ];
        }

        if ($usage <= 0) {
            return ['success' => false, 'error' => 'Pemakaian air (usage) harus lebih besar dari 0 m³.', 'data' => null];
        }

        // ── 6. Load water rate tiers ──────────────────────────────────────────
        $tiers = $this->waterRateTierModel
            ->where('water_rate_group_id', (int) $waterGroup['id'])
            ->orderBy('min_usage', 'ASC')
            ->findAll();

        if (empty($tiers)) {
            return ['success' => false, 'error' => 'Kelompok Tarif Air tidak memiliki tier/level harga yang dikonfigurasi.', 'data' => null];
        }

        // ── 7. Progressive tier calculation ───────────────────────────────────
        $remainingUsage = $usage;
        $usageCost      = 0.0;
        $tierSnapshots  = [];

        foreach ($tiers as $tier) {
            if ($remainingUsage <= 0) {
                break;
            }

            $minUsage  = (float) $tier['min_usage'];
            $maxUsage  = $tier['max_usage'] !== null ? (float) $tier['max_usage'] : null;
            $ratePerM3 = (float) $tier['rate_per_m3'];

            // Tier width: if max_usage is NULL, this is the open-ended final tier
            if ($maxUsage !== null) {
                $tierWidth   = $maxUsage - $minUsage;
                $usageInTier = min($remainingUsage, $tierWidth);
            } else {
                // Open-ended final tier: all remaining usage goes here
                $tierWidth   = null;
                $usageInTier = $remainingUsage;
            }

            $tierAmount = round($usageInTier * $ratePerM3, 2);
            $usageCost += $tierAmount;
            $remainingUsage -= $usageInTier;

            // Build label: e.g. "0–20 m³ @ Rp7.500/m³"
            $maxLabel = $maxUsage !== null ? number_format($maxUsage, 0, ',', '.') : '∞';
            $tierLabel = number_format($minUsage, 0, ',', '.') . '–' . $maxLabel . ' m³ @ Rp' . number_format($ratePerM3, 0, ',', '.') . '/m³';

            $tierSnapshots[] = [
                'tier_label'    => $tierLabel,
                'usage_in_tier' => round($usageInTier, 2),
                'rate'          => $ratePerM3,
                'amount'        => $tierAmount,
            ];
        }

        $usageCost = round($usageCost, 2);

        // ── 8. Add abonemen ───────────────────────────────────────────────────
        $abonemen = (float) ($waterGroup['abonemen'] ?? 0);
        $subtotal  = round($usageCost + $abonemen, 2);

        // ── 9. Duplicate check ────────────────────────────────────────────────
        if ($this->checkDuplicate($ownershipId, 'water', $periodStart, $periodEnd)) {
            return [
                'success' => false,
                'error'   => "Tagihan Air untuk kepemilikan ini pada periode {$periodStart} s/d {$periodEnd} sudah ada. Tidak dapat membuat tagihan duplikat.",
                'data'    => null,
            ];
        }

        // ── 10. Build description ─────────────────────────────────────────────
        $startYear  = date('Y', strtotime($periodStart));
        $startMonth = $this->monthNameId(date('n', strtotime($periodStart)));
        $description = "Tagihan Air {$startMonth} {$startYear}";
        if (!empty($data['description'])) {
            $description = trim($data['description']);
        }

        // ── 11. Insert billing_item snapshot ──────────────────────────────────
        // For water billing:
        //   quantity   = usage in m³
        //   unit_price = effective average cost per m³ (usageCost / usage, for record keeping)
        //   subtotal   = usageCost + abonemen  (authoritative)
        //   notes      = includes abonemen and water group name for audit
        $avgRate   = $usage > 0 ? round($usageCost / $usage, 4) : 0;
        $auditNote = "Pemakaian: {$usage} m³ | Abonemen: Rp" . number_format($abonemen, 0, ',', '.') . " | Tarif: {$waterGroup['name']}";
        if (!empty($data['notes'])) {
            $auditNote .= ' | ' . trim($data['notes']);
        }

        $insertData = [
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'water',
            'billing_period_start' => $periodStart,
            'billing_period_end'   => $periodEnd,
            'meter_reading_id'     => (int) $reading['id'],
            'description'          => $description,
            'quantity'             => $usage,          // m³ used — snapshot
            'unit'                 => 'm³',
            'unit_price'           => $avgRate,        // avg Rp/m³ for record
            'subtotal'             => $subtotal,       // authoritative: usageCost + abonemen
            'apply_tax'            => 1,
            'notes'                => $auditNote,
            'status'               => 'draft',
        ];

        $billingItemId = $this->model->insert($insertData, true);

        // ── 12. Insert billing_item_tiers snapshots ───────────────────────────
        $now = date('Y-m-d H:i:s');
        foreach ($tierSnapshots as $snap) {
            $this->tierModel->insert([
                'billing_item_id' => (int) $billingItemId,
                'tier_label'      => $snap['tier_label'],
                'usage_in_tier'   => $snap['usage_in_tier'],
                'rate'            => $snap['rate'],
                'amount'          => $snap['amount'],
                'created_at'      => $now,
            ]);
        }

        // Also insert the abonemen as a separate tier record for full auditability
        if ($abonemen > 0) {
            $this->tierModel->insert([
                'billing_item_id' => (int) $billingItemId,
                'tier_label'      => 'Abonemen',
                'usage_in_tier'   => 0,
                'rate'            => $abonemen,
                'amount'          => $abonemen,
                'created_at'      => $now,
            ]);
        }

        return [
            'success' => true,
            'error'   => null,
            'data'    => $this->model->findWithRelations((int) $billingItemId),
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
