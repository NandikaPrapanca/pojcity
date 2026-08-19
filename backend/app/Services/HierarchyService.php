<?php

namespace App\Services;

use App\Models\ClusterModel;
use App\Models\BlockModel;
use App\Models\LotModel;
use App\Models\ProjectModel;

class HierarchyService
{
    protected ClusterModel $clusterModel;
    protected BlockModel   $blockModel;
    protected LotModel     $lotModel;
    protected ProjectModel $projectModel;

    public function __construct()
    {
        $this->clusterModel = new ClusterModel();
        $this->blockModel   = new BlockModel();
        $this->lotModel     = new LotModel();
        $this->projectModel = new ProjectModel();
    }

    // ─── Clusters ────────────────────────────────────────────────────────────

    public function getClusters(array $filters = []): array
    {
        $builder = $this->clusterModel->builder();
        $builder->where('clusters.deleted_at IS NULL');

        if (!empty($filters['project_id'])) {
            $builder->where('project_id', (int) $filters['project_id']);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    public function getClusterById(int $id): ?array
    {
        return $this->clusterModel->find($id);
    }

    /**
     * Clusters may only be created under residential projects.
     * Returns ['success'=>bool, 'error'=>string|null, 'data'=>array|null]
     */
    public function createCluster(array $data): array
    {
        $project = $this->projectModel->find($data['project_id'] ?? 0);
        if (!$project) {
            return ['success' => false, 'error' => 'Proyek tidak ditemukan.', 'data' => null];
        }
        if ($project['project_type'] !== 'residential') {
            return ['success' => false, 'error' => 'Cluster hanya dapat dibuat untuk proyek residensial.', 'data' => null];
        }

        $id = $this->clusterModel->insert($data, true);
        return ['success' => true, 'error' => null, 'data' => $this->clusterModel->find($id)];
    }

    public function updateCluster(int $id, array $data): array
    {
        $cluster = $this->clusterModel->find($id);
        if (!$cluster) {
            return ['success' => false, 'error' => 'Cluster tidak ditemukan.', 'data' => null];
        }

        $this->clusterModel->update($id, $data);
        return ['success' => true, 'error' => null, 'data' => $this->clusterModel->find($id)];
    }

    public function deleteCluster(int $id): bool
    {
        return (bool) $this->clusterModel->delete($id);
    }

    public function getBlocksByCluster(int $clusterId): array
    {
        return $this->blockModel
            ->where('cluster_id', $clusterId)
            ->where('deleted_at IS NULL')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    // ─── Blocks ───────────────────────────────────────────────────────────────

    public function getBlocks(array $filters = []): array
    {
        $builder = $this->blockModel->builder();
        $builder->where('blocks.deleted_at IS NULL');

        if (!empty($filters['cluster_id'])) {
            $builder->where('cluster_id', (int) $filters['cluster_id']);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    public function getBlockById(int $id): ?array
    {
        return $this->blockModel->find($id);
    }

    public function createBlock(array $data): array
    {
        $cluster = $this->clusterModel->find($data['cluster_id'] ?? 0);
        if (!$cluster) {
            return ['success' => false, 'error' => 'Cluster tidak ditemukan.', 'data' => null];
        }

        $id = $this->blockModel->insert($data, true);
        return ['success' => true, 'error' => null, 'data' => $this->blockModel->find($id)];
    }

    public function updateBlock(int $id, array $data): array
    {
        $block = $this->blockModel->find($id);
        if (!$block) {
            return ['success' => false, 'error' => 'Block tidak ditemukan.', 'data' => null];
        }

        $this->blockModel->update($id, $data);
        return ['success' => true, 'error' => null, 'data' => $this->blockModel->find($id)];
    }

    public function deleteBlock(int $id): bool
    {
        return (bool) $this->blockModel->delete($id);
    }

    public function getLotsByBlock(int $blockId): array
    {
        return $this->lotModel
            ->where('block_id', $blockId)
            ->where('deleted_at IS NULL')
            ->orderBy('lot_number', 'ASC')
            ->findAll();
    }

    // ─── Lots ─────────────────────────────────────────────────────────────────

    public function getLots(array $filters = []): array
    {
        $builder = $this->lotModel->builder();
        $builder->where('lots.deleted_at IS NULL');

        if (!empty($filters['block_id'])) {
            $builder->where('block_id', (int) $filters['block_id']);
        }

        return $builder->orderBy('lot_number', 'ASC')->get()->getResultArray();
    }

    public function getLotById(int $id): ?array
    {
        return $this->lotModel->find($id);
    }

    public function createLot(array $data): array
    {
        $block = $this->blockModel->find($data['block_id'] ?? 0);
        if (!$block) {
            return ['success' => false, 'error' => 'Block tidak ditemukan.', 'data' => null];
        }

        $id = $this->lotModel->insert($data, true);
        return ['success' => true, 'error' => null, 'data' => $this->lotModel->find($id)];
    }

    public function updateLot(int $id, array $data): array
    {
        $lot = $this->lotModel->find($id);
        if (!$lot) {
            return ['success' => false, 'error' => 'Lot tidak ditemukan.', 'data' => null];
        }

        $this->lotModel->update($id, $data);
        return ['success' => true, 'error' => null, 'data' => $this->lotModel->find($id)];
    }

    public function deleteLot(int $id): bool
    {
        return (bool) $this->lotModel->delete($id);
    }
}
