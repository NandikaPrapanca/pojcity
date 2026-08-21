<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table      = 'invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'invoice_number',
        'company_id',
        'ownership_id',
        'customer_id',
        'created_by',
        'issue_date',
        'due_date',
        'subtotal_dpp',
        'dpp_nilai_lain',
        'ppn_amount',
        'grand_total',
        'tax_applied',
        'ppn_rate',
        'notes',
        'status',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    /**
     * Get all active invoices with enriched relations.
     *
     * @param array $filters  Supported keys: company_id, ownership_id, customer_id,
     *                        status, issue_date_from, issue_date_to, search
     */
    public function getAllWithRelations(array $filters = []): array
    {
        $builder = $this->db->table('invoices')
            ->select('
                invoices.*,
                companies.name            AS company_name,
                customers.name            AS customer_name,
                customers.customer_type,
                customers.whatsapp        AS customer_whatsapp,
                customers.email           AS customer_email,
                projects.name             AS project_name,
                clusters.name             AS cluster_name,
                blocks.name               AS block_name,
                lots.lot_number,
                ownerships.ownership_type,
                ownerships.billing_address
            ')
            ->join('companies',   'companies.id   = invoices.company_id',    'left')
            ->join('customers',   'customers.id   = invoices.customer_id',   'left')
            ->join('ownerships',  'ownerships.id  = invoices.ownership_id',  'left')
            ->join('projects',    'projects.id    = ownerships.project_id',  'left')
            ->join('clusters',    'clusters.id    = ownerships.cluster_id',  'left')
            ->join('blocks',      'blocks.id      = ownerships.block_id',    'left')
            ->join('lots',        'lots.id        = ownerships.lot_id',      'left')
            ->where('invoices.deleted_at IS NULL');

        if (!empty($filters['company_id'])) {
            $builder->where('invoices.company_id', (int) $filters['company_id']);
        }
        if (!empty($filters['ownership_id'])) {
            $builder->where('invoices.ownership_id', (int) $filters['ownership_id']);
        }
        if (!empty($filters['customer_id'])) {
            $builder->where('invoices.customer_id', (int) $filters['customer_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('invoices.status', strtolower($filters['status']));
        }
        if (!empty($filters['issue_date_from'])) {
            $builder->where('invoices.issue_date >=', $filters['issue_date_from']);
        }
        if (!empty($filters['issue_date_to'])) {
            $builder->where('invoices.issue_date <=', $filters['issue_date_to']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $builder->groupStart()
                ->like('invoices.invoice_number', $s)
                ->orLike('customers.name', $s)
                ->orLike('invoices.notes', $s)
                ->groupEnd();
        }

        return $builder
            ->orderBy('invoices.issue_date', 'DESC')
            ->orderBy('invoices.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get a single invoice with all relations and its line items.
     */
    public function findWithRelations(int $id): ?array
    {
        $invoice = $this->db->table('invoices')
            ->select('
                invoices.*,
                companies.name            AS company_name,
                companies.address         AS company_address,
                companies.phone           AS company_phone,
                companies.npwp            AS company_npwp,
                customers.name            AS customer_name,
                customers.customer_type,
                customers.whatsapp        AS customer_whatsapp,
                customers.email           AS customer_email,
                customers.npwp            AS customer_npwp,
                projects.name             AS project_name,
                clusters.name             AS cluster_name,
                blocks.name               AS block_name,
                lots.lot_number,
                ownerships.ownership_type,
                ownerships.billing_address
            ')
            ->join('companies',   'companies.id   = invoices.company_id',    'left')
            ->join('customers',   'customers.id   = invoices.customer_id',   'left')
            ->join('ownerships',  'ownerships.id  = invoices.ownership_id',  'left')
            ->join('projects',    'projects.id    = ownerships.project_id',  'left')
            ->join('clusters',    'clusters.id    = ownerships.cluster_id',  'left')
            ->join('blocks',      'blocks.id      = ownerships.block_id',    'left')
            ->join('lots',        'lots.id        = ownerships.lot_id',      'left')
            ->where('invoices.id', $id)
            ->where('invoices.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$invoice) {
            return null;
        }

        // Attach line items
        $invoice['items'] = $this->db->table('invoice_items')
            ->where('invoice_id', $id)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return $invoice;
    }

    /**
     * Find invoice by invoice_number (e.g. for duplicate guard).
     */
    public function findByNumber(string $invoiceNumber): ?array
    {
        return $this->where('invoice_number', $invoiceNumber)
                    ->where('deleted_at IS NULL')
                    ->first();
    }
}
