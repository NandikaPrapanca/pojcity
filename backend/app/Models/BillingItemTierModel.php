<?php

namespace App\Models;

use CodeIgniter\Model;

class BillingItemTierModel extends Model
{
    protected $table         = 'billing_item_tiers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'billing_item_id',
        'tier_label',
        'usage_in_tier',
        'rate',
        'amount',
        'created_at',
    ];

    public function getForBillingItem(int $billingItemId): array
    {
        return $this->where('billing_item_id', $billingItemId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }
}
