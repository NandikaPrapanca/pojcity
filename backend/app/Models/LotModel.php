<?php

namespace App\Models;

use CodeIgniter\Model;

class LotModel extends Model
{
    protected $table      = 'lots';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'block_id', 'lot_number', 'area', 'notes',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;
}
