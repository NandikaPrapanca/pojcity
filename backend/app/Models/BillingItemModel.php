<?php

namespace App\Models;

use CodeIgniter\Model;

class BillingItemModel extends Model
{
    protected $table      = 'billing_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'ownership_id',
        'billing_type',
        'billing_period_start',
        'billing_period_end',
        'meter_reading_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'subtotal',
        'management_fee_rate',
        'management_fee_amount',
        'pln_charge',
        'apply_tax',
        'notes',
        'status',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;

    /**
     * Find billing item with all related data joined for display.
     */
    public function findWithRelations(int $id): ?array
    {
        $row = $this->find($id);
        if (!$row) {
            return null;
        }

        return $this->enrichBillingItem($row);
    }

    /**
     * Get all active billing items with optional filters, enriched with relations.
     */
    public function getAllWithRelations(array $filters = []): array
    {
        $builder = $this->builder();
        $builder->where('billing_items.deleted_at IS NULL');

        if (!empty($filters['ownership_id'])) {
            $builder->where('billing_items.ownership_id', (int) $filters['ownership_id']);
        }

        if (!empty($filters['billing_type'])) {
            $builder->where('billing_items.billing_type', strtolower($filters['billing_type']));
        }

        if (!empty($filters['status'])) {
            $builder->where('billing_items.status', strtolower($filters['status']));
        }

        if (!empty($filters['billing_period_start'])) {
            $builder->where('billing_items.billing_period_start >=', $filters['billing_period_start']);
        }

        if (!empty($filters['billing_period_end'])) {
            $builder->where('billing_items.billing_period_end <=', $filters['billing_period_end']);
        }

        if (!empty($filters['period'])) {
            // Filter by month/year prefix (YYYY-MM)
            $period = $filters['period'];
            $builder->groupStart()
                ->like('billing_items.billing_period_start', $period, 'after')
                ->orLike('billing_items.billing_period_end', $period, 'after')
                ->groupEnd();
        }

        if (!empty($filters['project_id'])) {
            $projectId = (int) $filters['project_id'];
            $builder->where('billing_items.ownership_id IN (
                SELECT o.id FROM ownerships o WHERE o.project_id = ' . $projectId . ' AND o.deleted_at IS NULL
            )');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->groupStart()
                ->like('billing_items.description', $search)
                ->orWhere('billing_items.ownership_id IN (
                    SELECT o.id FROM ownerships o
                    JOIN customers c ON c.id = o.customer_id
                    LEFT JOIN projects p ON p.id = o.project_id
                    LEFT JOIN clusters cl ON cl.id = o.cluster_id
                    LEFT JOIN blocks bl ON bl.id = o.block_id
                    LEFT JOIN lots lt ON lt.id = o.lot_id
                    WHERE (
                        c.name LIKE ' . $this->db->escape('%' . $search . '%') . '
                        OR p.name LIKE ' . $this->db->escape('%' . $search . '%') . '
                        OR cl.name LIKE ' . $this->db->escape('%' . $search . '%') . '
                        OR bl.name LIKE ' . $this->db->escape('%' . $search . '%') . '
                        OR lt.lot_number LIKE ' . $this->db->escape('%' . $search . '%') . '
                    ) AND o.deleted_at IS NULL
                )')
                ->groupEnd();
        }

        $rows = $builder->orderBy('billing_items.billing_period_start', 'DESC')
                        ->orderBy('billing_items.id', 'DESC')
                        ->get()
                        ->getResultArray();

        return array_map([$this, 'enrichBillingItem'], $rows);
    }

    /**
     * Check if an active/invoiced billing item exists with identical ownership, type, and period.
     * Documented duplicate check logic:
     * - Same ownership_id
     * - Same billing_type (case-insensitive)
     * - Same billing_period_start AND billing_period_end
     * - Excludes soft-deleted records (deleted_at IS NULL)
     * - Excludes cancelled items
     * - Excludes current item when updating ($excludeId)
     */
    public function findDuplicate(int $ownershipId, string $billingType, string $periodStart, string $periodEnd, ?int $excludeId = null): ?array
    {
        $builder = $this->builder();
        $builder->where('ownership_id', $ownershipId)
                ->where('billing_type', strtolower($billingType))
                ->where('billing_period_start', $periodStart)
                ->where('billing_period_end', $periodEnd)
                ->where('status !=', 'cancelled')
                ->where('deleted_at IS NULL');

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->get()->getRowArray() ?: null;
    }

    /**
     * Enrich a single billing item with ownership, customer, project, lot, and meter reading details.
     */
    public function enrichBillingItem(array $item): array
    {
        $ownership = $this->db->table('ownerships')
            ->select('ownerships.*,
                      customers.name as customer_name,
                      customers.customer_type,
                      customers.whatsapp as customer_whatsapp,
                      customers.email as customer_email,
                      projects.name as project_name,
                      projects.project_type,
                      clusters.name as cluster_name,
                      blocks.name as block_name,
                      lots.lot_number')
            ->join('customers', 'customers.id = ownerships.customer_id', 'left')
            ->join('projects', 'projects.id = ownerships.project_id', 'left')
            ->join('clusters', 'clusters.id = ownerships.cluster_id', 'left')
            ->join('blocks', 'blocks.id = ownerships.block_id', 'left')
            ->join('lots', 'lots.id = ownerships.lot_id', 'left')
            ->where('ownerships.id', (int) $item['ownership_id'])
            ->get()
            ->getRowArray();

        $item['customer_id']        = $ownership ? (int) $ownership['customer_id'] : null;
        $item['customer_name']      = $ownership['customer_name'] ?? null;
        $item['customer_type']      = $ownership['customer_type'] ?? null;
        $item['customer_whatsapp']  = $ownership['customer_whatsapp'] ?? null;
        $item['customer_email']     = $ownership['customer_email'] ?? null;
        $item['project_id']         = $ownership ? (int) $ownership['project_id'] : null;
        $item['project_name']       = $ownership['project_name'] ?? null;
        $item['project_type']       = $ownership['project_type'] ?? null;
        $item['ownership_type']     = $ownership['ownership_type'] ?? null;
        $item['cluster_name']       = $ownership['cluster_name'] ?? null;
        $item['block_name']         = $ownership['block_name'] ?? null;
        $item['lot_number']         = $ownership['lot_number'] ?? null;
        $item['area']               = $ownership['area'] ?? null;
        $item['billing_address']    = $ownership['billing_address'] ?? null;

        // Meter reading details if water reading is linked
        if (!empty($item['meter_reading_id'])) {
            $reading = $this->db->table('meter_readings')
                ->where('id', (int) $item['meter_reading_id'])
                ->get()
                ->getRowArray();

            $item['meter_reading'] = $reading ?: null;
        } else {
            $item['meter_reading'] = null;
        }

        // Tiers if any
        $tiers = $this->db->table('billing_item_tiers')
            ->where('billing_item_id', (int) $item['id'])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $item['tiers'] = $tiers ?: [];

        return $item;
    }
}
