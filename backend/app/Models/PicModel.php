<?php

namespace App\Models;

use CodeIgniter\Model;

class PicModel extends Model
{
    protected $table      = 'pics';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'customer_id', 'name', 'phone', 'email', 'position', 'is_primary',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;
}
