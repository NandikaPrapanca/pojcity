<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'name', 'email', 'password', 'role_id', 'is_active', 'last_login_at',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;

    protected $hidden = ['password'];

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)
                    ->where('deleted_at IS NULL')
                    ->first();
    }

    public function findActiveById(int $id): ?array
    {
        return $this->where('id', $id)
                    ->where('is_active', 1)
                    ->where('deleted_at IS NULL')
                    ->first();
    }
}
