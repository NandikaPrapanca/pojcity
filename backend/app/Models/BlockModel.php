<?php

namespace App\Models;

use CodeIgniter\Model;

class BlockModel extends Model
{
    protected $table      = 'blocks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'cluster_id', 'name',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;
}
