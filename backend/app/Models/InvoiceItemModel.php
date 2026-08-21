<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * InvoiceItemModel
 *
 * Handles the immutable snapshot rows in invoice_items.
 * - NO soft deletes (intentional — these rows are permanent legal records).
 * - NO update timestamp (insert-only by design).
 *
 * Only InvoiceService should write to this table; all other code is read-only.
 */
class InvoiceItemModel extends Model
{
    protected $table      = 'invoice_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'invoice_id',
        'billing_item_id',
        'billing_type',
        'billing_period_start',
        'billing_period_end',
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
    ];

    // Immutable records — no update tracking, no soft deletes.
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = ''; // disable update timestamp
    protected $useSoftDeletes = false;

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    /**
     * Get all line items for a given invoice, ordered by insertion order.
     */
    public function getByInvoiceId(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }

    /**
     * Insert a batch of snapshot rows.
     * Wraps insertBatch for convenience; caller should wrap in a transaction.
     *
     * @param  array<int, array> $items  Array of row data arrays.
     * @return bool
     */
    public function insertSnapshot(array $items): bool
    {
        if (empty($items)) {
            return true;
        }

        $now = date('Y-m-d H:i:s');

        // Stamp created_at on each row
        $rows = array_map(static function (array $item) use ($now): array {
            $item['created_at'] = $now;
            return $item;
        }, $items);

        return $this->insertBatch($rows) !== false;
    }
}
