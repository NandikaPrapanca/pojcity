<?php

namespace App\Models;

use CodeIgniter\Model;

class WaterRateGroupModel extends Model
{
    protected $table      = 'water_rate_groups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'project_id', 'name', 'abonemen',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;
}
