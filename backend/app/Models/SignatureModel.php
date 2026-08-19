<?php

namespace App\Models;

use CodeIgniter\Model;

class SignatureModel extends Model
{
    protected $table      = 'signatures';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'company_id', 'label', 'name', 'position', 'signature_path', 'is_active',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;
}
