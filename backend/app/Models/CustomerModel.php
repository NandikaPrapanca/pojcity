<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table      = 'customers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'company_id', 'name', 'customer_type', 'nik', 'npwp',
        'whatsapp', 'email', 'billing_address', 'notes',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $useSoftDeletes = true;

    /**
     * Find customer with their PICs.
     */
    public function findWithPics(int $id): ?array
    {
        $customer = $this->find($id);
        if (!$customer) {
            return null;
        }

        $picModel         = new PicModel();
        $customer['pics'] = $picModel->where('customer_id', $id)
                                     ->where('deleted_at IS NULL')
                                     ->findAll();

        return $customer;
    }
}
