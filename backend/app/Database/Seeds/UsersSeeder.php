<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run()
    {
        // Password is loaded from environment or falls back to dev default.
        // NEVER use this password in production.
        $devPassword = env('DEV_SEED_PASSWORD') ?: 'dev_password_change_me';

        $data = [
            [
                'name'       => 'Developer',
                'email'      => 'dev@ipu-billing.local',
                'password'   => password_hash($devPassword, PASSWORD_BCRYPT),
                'role_id'    => 1,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->insertBatch($data);
    }
}
