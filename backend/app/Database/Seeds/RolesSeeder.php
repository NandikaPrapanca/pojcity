<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $roles = [
            [
                'id'          => 1,
                'name'        => 'developer',
                'description' => 'Full system access including user management and settings.',
            ],
            [
                'id'          => 2,
                'name'        => 'admin',
                'description' => 'Operational access: billing, invoices, payments, reports.',
            ],
        ];

        foreach ($roles as $r) {
            $exists = $this->db->table('roles')->where('id', $r['id'])->countAllResults();
            if (!$exists) {
                $this->db->table('roles')->insert(array_merge($r, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
