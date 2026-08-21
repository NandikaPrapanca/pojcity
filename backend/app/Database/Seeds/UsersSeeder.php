<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $users = [
            [
                'name'     => 'Developer',
                'email'    => 'dev@demo.com',
                'password' => password_hash('dev123', PASSWORD_BCRYPT),
                'role_id'  => 1,
            ],
            [
                'name'     => 'Admin Satu',
                'email'    => 'admin1@demo.com',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'role_id'  => 2,
            ],
            [
                'name'     => 'Admin Dua',
                'email'    => 'admin2@demo.com',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'role_id'  => 2,
            ],
        ];

        foreach ($users as $user) {
            $this->db->query(
                "INSERT INTO users (name, email, password, role_id, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     name       = VALUES(name),
                     password   = VALUES(password),
                     role_id    = VALUES(role_id),
                     is_active  = VALUES(is_active),
                     updated_at = VALUES(updated_at)",
                [
                    $user['name'],
                    $user['email'],
                    $user['password'],
                    $user['role_id'],
                    $now,
                    $now,
                ]
            );
        }
    }
}
