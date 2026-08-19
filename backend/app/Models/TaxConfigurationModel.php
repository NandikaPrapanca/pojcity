<?php

namespace App\Models;

use CodeIgniter\Model;

class TaxConfigurationModel extends Model
{
    protected $table      = 'tax_configurations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'label', 'dpp_multiplier_numerator', 'dpp_multiplier_denominator',
        'ppn_rate', 'effective_date', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // No soft delete for tax_configurations
    protected $useSoftDeletes = false;

    public function getActive(): ?array
    {
        return $this->where('is_active', 1)->first();
    }
}
