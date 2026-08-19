<?php

namespace App\Models;

use CodeIgniter\Model;

class OwnershipModel extends Model
{
    protected $table      = 'ownerships';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'customer_id', 'project_id', 'cluster_id', 'block_id', 'lot_id',
        'billing_address', 'area', 'ipl_rate_id', 'water_rate_group_id',
        'ownership_type', 'start_date', 'end_date', 'notes',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;

    /**
     * Find with all related data joined for display.
     */
    public function findWithRelations(int $id): ?array
    {
        $row = $this->find($id);
        if (!$row) return null;

        return $this->enrichOwnership($row);
    }

    /**
     * Get all active ownerships with optional filters, enriched with relations.
     */
    public function getAllWithRelations(array $filters = []): array
    {
        $builder = $this->builder();
        $builder->where('ownerships.deleted_at IS NULL');

        if (!empty($filters['customer_id'])) {
            $builder->where('ownerships.customer_id', (int) $filters['customer_id']);
        }
        if (!empty($filters['project_id'])) {
            $builder->where('ownerships.project_id', (int) $filters['project_id']);
        }
        if (!empty($filters['ownership_type'])) {
            $builder->where('ownerships.ownership_type', $filters['ownership_type']);
        }
        if (isset($filters['active'])) {
            if ($filters['active'] === 'true' || $filters['active'] === '1') {
                $builder->where('ownerships.end_date IS NULL');
            } elseif ($filters['active'] === 'false' || $filters['active'] === '0') {
                $builder->where('ownerships.end_date IS NOT NULL');
            }
        }
        if (!empty($filters['search'])) {
            // Search by customer name via subquery
            $builder->groupStart()
                ->where('ownerships.customer_id IN (SELECT id FROM customers WHERE name LIKE ' . $this->db->escape('%' . $filters['search'] . '%') . ' AND deleted_at IS NULL)')
                ->groupEnd();
        }

        $rows = $builder->orderBy('ownerships.id', 'DESC')->get()->getResultArray();
        return array_map([$this, 'enrichOwnership'], $rows);
    }

    /**
     * Check for an existing active (non-deleted, no end_date) ownership
     * that would duplicate the new one.
     */
    public function findDuplicate(int $customerId, int $projectId, ?int $lotId, ?int $excludeId = null): ?array
    {
        $builder = $this->builder();
        $builder->where('customer_id', $customerId)
                ->where('project_id', $projectId)
                ->where('deleted_at IS NULL')
                ->where('end_date IS NULL'); // only active ownerships

        if ($lotId !== null) {
            $builder->where('lot_id', $lotId);
        }
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->get()->getRowArray() ?: null;
    }

    /**
     * Enrich a single ownership row with human-readable related data.
     */
    private function enrichOwnership(array $row): array
    {
        // Customer
        $customer = $this->db->table('customers')->where('id', $row['customer_id'])->get()->getRowArray();
        $row['customer_name'] = $customer['name'] ?? null;

        // Project
        $project = $this->db->table('projects')->where('id', $row['project_id'])->get()->getRowArray();
        $row['project_name'] = $project['name'] ?? null;
        $row['project_type'] = $project['project_type'] ?? null;

        // Cluster
        if ($row['cluster_id']) {
            $cluster = $this->db->table('clusters')->where('id', $row['cluster_id'])->get()->getRowArray();
            $row['cluster_name'] = $cluster['name'] ?? null;
        } else {
            $row['cluster_name'] = null;
        }

        // Block
        if ($row['block_id']) {
            $block = $this->db->table('blocks')->where('id', $row['block_id'])->get()->getRowArray();
            $row['block_name'] = $block['name'] ?? null;
        } else {
            $row['block_name'] = null;
        }

        // Lot
        if ($row['lot_id']) {
            $lot = $this->db->table('lots')->where('id', $row['lot_id'])->get()->getRowArray();
            $row['lot_number'] = $lot['lot_number'] ?? null;
        } else {
            $row['lot_number'] = null;
        }

        // IPL Rate
        $ipl = $this->db->table('ipl_rates')->where('id', $row['ipl_rate_id'])->get()->getRowArray();
        $row['ipl_rate_name']    = $ipl['name'] ?? null;
        $row['ipl_rate_per_sqm'] = $ipl['rate_per_sqm'] ?? null;

        // Water Rate Group
        if ($row['water_rate_group_id']) {
            $wg = $this->db->table('water_rate_groups')->where('id', $row['water_rate_group_id'])->get()->getRowArray();
            $row['water_rate_group_name'] = $wg['name'] ?? null;
        } else {
            $row['water_rate_group_name'] = null;
        }

        return $row;
    }
}
