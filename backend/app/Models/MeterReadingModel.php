<?php

namespace App\Models;

use CodeIgniter\Model;

class MeterReadingModel extends Model
{
    protected $table      = 'meter_readings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
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

        return $this->enrichMeterReading($row);
    }

    /**
     * Get all active meter readings with optional filters, enriched with relations.
     */
    public function getAllWithRelations(array $filters = []): array
    {
        $builder = $this->builder();
        $builder->where('meter_readings.deleted_at IS NULL');

        if (!empty($filters['ownership_id'])) {
            $builder->where('meter_readings.ownership_id', (int) $filters['ownership_id']);
        }

        if (!empty($filters['period'])) {
            // Filter by month/year prefix (YYYY-MM)
            $period = $filters['period'];
            $builder->groupStart()
                ->like('meter_readings.billing_period_start', $period, 'after')
                ->orLike('meter_readings.reading_date', $period, 'after')
                ->groupEnd();
        }

        if (!empty($filters['search'])) {
            // Search by customer name, project name, or meter number
            $search = $filters['search'];
            $builder->groupStart()
                ->like('meter_readings.meter_number', $search)
                ->orWhere('meter_readings.ownership_id IN (
                    SELECT o.id FROM ownerships o
                    JOIN customers c ON c.id = o.customer_id
                    WHERE c.name LIKE ' . $this->db->escape('%' . $search . '%') . ' AND c.deleted_at IS NULL
                )')
                ->groupEnd();
        }

        $rows = $builder->orderBy('meter_readings.reading_date', 'DESC')
                        ->orderBy('meter_readings.id', 'DESC')
                        ->get()
                        ->getResultArray();

        return array_map([$this, 'enrichMeterReading'], $rows);
    }

    /**
     * Get the latest meter reading for an ownership.
     */
    public function getLatestForOwnership(int $ownershipId): ?array
    {
        $builder = $this->builder();
        $row = $builder->where('ownership_id', $ownershipId)
            ->where('deleted_at IS NULL')
            ->orderBy('reading_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Check for potential billing period overlap for the same ownership.
     */
    public function findOverlapping(int $ownershipId, string $startDate, string $endDate, ?int $excludeId = null): ?array
    {
        $builder = $this->builder();
        $builder->where('ownership_id', $ownershipId)
            ->where('deleted_at IS NULL')
            ->where('billing_period_start <=', $endDate)
            ->where('billing_period_end >=', $startDate);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->get()->getRowArray() ?: null;
    }

    /**
     * Enrich a single meter reading row with human-readable related data.
     */
    private function enrichMeterReading(array $row): array
    {
        $ownership = $this->db->table('ownerships')->where('id', $row['ownership_id'])->get()->getRowArray();
        if ($ownership) {
            $row['customer_id'] = $ownership['customer_id'];
            $row['project_id']  = $ownership['project_id'];
            $row['ownership_type'] = $ownership['ownership_type'];

            // Customer
            $customer = $this->db->table('customers')->where('id', $ownership['customer_id'])->get()->getRowArray();
            $row['customer_name'] = $customer['name'] ?? null;
            $row['customer_phone'] = $customer['whatsapp'] ?? null;

            // Project
            $project = $this->db->table('projects')->where('id', $ownership['project_id'])->get()->getRowArray();
            $row['project_name'] = $project['name'] ?? null;

            // Cluster
            if (!empty($ownership['cluster_id'])) {
                $cluster = $this->db->table('clusters')->where('id', $ownership['cluster_id'])->get()->getRowArray();
                $row['cluster_name'] = $cluster['name'] ?? null;
            } else {
                $row['cluster_name'] = null;
            }

            // Block
            if (!empty($ownership['block_id'])) {
                $block = $this->db->table('blocks')->where('id', $ownership['block_id'])->get()->getRowArray();
                $row['block_name'] = $block['name'] ?? null;
            } else {
                $row['block_name'] = null;
            }

            // Lot
            if (!empty($ownership['lot_id'])) {
                $lot = $this->db->table('lots')->where('id', $ownership['lot_id'])->get()->getRowArray();
                $row['lot_number'] = $lot['lot_number'] ?? null;
            } else {
                $row['lot_number'] = null;
            }

            // Water Rate Group
            if (!empty($ownership['water_rate_group_id'])) {
                $wg = $this->db->table('water_rate_groups')->where('id', $ownership['water_rate_group_id'])->get()->getRowArray();
                $row['water_rate_group_name'] = $wg['name'] ?? null;
            } else {
                $row['water_rate_group_name'] = null;
            }
        } else {
            $row['customer_name'] = null;
            $row['project_name'] = null;
            $row['lot_number'] = null;
        }

        return $row;
    }
}
