<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table      = 'payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'invoice_id',
        'amount',
        'payment_method',
        'payment_date',
        'reference_number',
        'notes',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    /**
     * Get payment for an invoice.
     */
    public function getForInvoice(int $invoiceId): ?array
    {
        return $this->where('invoice_id', $invoiceId)
                    ->orderBy('id', 'DESC')
                    ->first();
    }
}
