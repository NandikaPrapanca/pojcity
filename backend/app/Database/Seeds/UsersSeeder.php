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
        $hash        = password_hash($devPassword, PASSWORD_BCRYPT);
        $now         = date('Y-m-d H:i:s');

        // Use UPSERT so this seeder is idempotent — safe to re-run after tests
        // without throwing a duplicate key error.
        $this->db->query(
            "INSERT INTO users (name, email, password, role_id, is_active, created_at, updated_at)
             VALUES (?, 'dev@ipu-billing.local', ?, 1, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                 password   = VALUES(password),
                 updated_at = VALUES(updated_at)",
            ['Developer', $hash, $now, $now]
        );
    }
}
