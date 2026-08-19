<?php

namespace App\Models;

use CodeIgniter\Model;

class IplRateModel extends Model
{
    protected $table      = 'ipl_rates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'project_id', 'name', 'rate_per_sqm', 'effective_date', 'notes',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;
}
