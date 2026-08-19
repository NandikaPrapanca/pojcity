<?php

namespace App\Models;

use CodeIgniter\Model;

class WaterRateTierModel extends Model
{
    protected $table      = 'water_rate_tiers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'water_rate_group_id', 'min_usage', 'max_usage', 'rate_per_m3',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // No soft delete for water_rate_tiers
    protected $useSoftDeletes = false;
}
