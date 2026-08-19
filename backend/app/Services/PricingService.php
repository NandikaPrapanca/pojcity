<?php

namespace App\Services;

use App\Models\IplRateModel;
use App\Models\WaterRateGroupModel;
use App\Models\WaterRateTierModel;

class PricingService
{
    protected IplRateModel       $iplRateModel;
    protected WaterRateGroupModel $groupModel;
    protected WaterRateTierModel  $tierModel;

    public function __construct()
    {
        $this->iplRateModel = new IplRateModel();
        $this->groupModel   = new WaterRateGroupModel();
        $this->tierModel    = new WaterRateTierModel();
    }

    // ─── IPL Rates ────────────────────────────────────────────────────────────

    public function getIplRates(array $filters = []): array
    {
        $builder = $this->iplRateModel->builder();
        $builder->where('ipl_rates.deleted_at IS NULL');

        if (!empty($filters['project_id'])) {
            $builder->where('project_id', (int) $filters['project_id']);
        }

        return $builder->orderBy('effective_date', 'DESC')->get()->getResultArray();
    }

    public function getIplRateById(int $id): ?array
    {
        return $this->iplRateModel->find($id);
    }

    public function createIplRate(array $data): array
    {
        $id = $this->iplRateModel->insert($data, true);
        return $this->iplRateModel->find($id);
    }

    public function updateIplRate(int $id, array $data): ?array
    {
        $rate = $this->iplRateModel->find($id);
        if (!$rate) return null;

        $this->iplRateModel->update($id, $data);
        return $this->iplRateModel->find($id);
    }

    public function deleteIplRate(int $id): bool
    {
        return (bool) $this->iplRateModel->delete($id);
    }

    // ─── Water Rate Groups ────────────────────────────────────────────────────

    public function getWaterRateGroups(array $filters = []): array
    {
        $builder = $this->groupModel->builder();
        $builder->where('water_rate_groups.deleted_at IS NULL');

        if (!empty($filters['project_id'])) {
            $builder->where('project_id', (int) $filters['project_id']);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    public function getWaterRateGroupById(int $id): ?array
    {
        $group = $this->groupModel->find($id);
        if (!$group) return null;

        $group['tiers'] = $this->getTiersForGroup($id);
        return $group;
    }

    public function createWaterRateGroup(array $data): array
    {
        $id = $this->groupModel->insert($data, true);
        return $this->getWaterRateGroupById((int) $id);
    }

    public function updateWaterRateGroup(int $id, array $data): ?array
    {
        $group = $this->groupModel->find($id);
        if (!$group) return null;

        $this->groupModel->update($id, $data);
        return $this->getWaterRateGroupById($id);
    }

    public function deleteWaterRateGroup(int $id): bool
    {
        return (bool) $this->groupModel->delete($id);
    }

    // ─── Water Rate Tiers ─────────────────────────────────────────────────────

    public function getTiersForGroup(int $groupId): array
    {
        return $this->tierModel
            ->where('water_rate_group_id', $groupId)
            ->orderBy('min_usage', 'ASC')
            ->findAll();
    }

    /**
     * Create a new tier and validate no gaps/overlaps.
     * Returns ['success'=>bool, 'error'=>string|null, 'data'=>array|null]
     */
    public function createTier(int $groupId, array $data): array
    {
        $group = $this->groupModel->find($groupId);
        if (!$group) {
            return ['success' => false, 'error' => 'Grup tarif air tidak ditemukan.', 'data' => null];
        }

        $data['water_rate_group_id'] = $groupId;

        // Validate against existing tiers
        $validation = $this->validateTierAgainstExisting($groupId, $data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        $id = $this->tierModel->insert($data, true);
        return ['success' => true, 'error' => null, 'data' => $this->tierModel->find($id)];
    }

    public function updateTier(int $id, array $data): array
    {
        $tier = $this->tierModel->find($id);
        if (!$tier) {
            return ['success' => false, 'error' => 'Tier tidak ditemukan.', 'data' => null];
        }

        // Merge with existing values for validation
        $mergedData = array_merge($tier, $data);

        $validation = $this->validateTierAgainstExisting(
            (int) $tier['water_rate_group_id'],
            $mergedData,
            $id // exclude self
        );
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'data' => null];
        }

        $this->tierModel->update($id, $data);
        return ['success' => true, 'error' => null, 'data' => $this->tierModel->find($id)];
    }

    public function deleteTier(int $id): bool
    {
        return (bool) $this->tierModel->delete($id);
    }

    /**
     * Validates that a new/updated tier does not overlap or create a gap
     * with existing tiers in the same group.
     */
    private function validateTierAgainstExisting(int $groupId, array $newTier, ?int $excludeId = null): array
    {
        $builder = $this->tierModel->where('water_rate_group_id', $groupId);
        if ($excludeId !== null) {
            $builder = $builder->where('id !=', $excludeId);
        }
        $existing = $builder->orderBy('min_usage', 'ASC')->findAll();

        $newMin = (float) $newTier['min_usage'];
        $newMax = isset($newTier['max_usage']) && $newTier['max_usage'] !== null && $newTier['max_usage'] !== ''
            ? (float) $newTier['max_usage']
            : null;

        if ($newMax !== null && $newMax <= $newMin) {
            return ['valid' => false, 'error' => 'Maksimum penggunaan harus lebih besar dari minimum.'];
        }

        foreach ($existing as $tier) {
            $eMin = (float) $tier['min_usage'];
            $eMax = $tier['max_usage'] !== null ? (float) $tier['max_usage'] : PHP_FLOAT_MAX;

            // Check overlap: new tier's range overlaps existing
            $newMaxForCheck = $newMax ?? PHP_FLOAT_MAX;
            if ($newMin < $eMax && $newMaxForCheck > $eMin) {
                return [
                    'valid' => false,
                    'error' => "Tier baru tumpang tindih dengan tier yang sudah ada ({$tier['min_usage']}–" .
                               ($tier['max_usage'] ?? '∞') . ").",
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }
}
