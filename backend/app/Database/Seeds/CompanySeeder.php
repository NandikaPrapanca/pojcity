<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $name = 'PT Integrasi Prasarana Lingkungan';

        $exists = $this->db->table('companies')
            ->where('name', $name)
            ->countAllResults();

        if (!$exists) {
            $this->db->table('companies')->insert([
                'name'       => $name,
                'address'    => 'Jl. Poj City No.1, Semarang, Jawa Tengah',
                'phone'      => '024-1234567',
                'email'      => 'admin@ipu-land.local',
                'npwp'       => '12.345.678.9-012.000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            echo "CompanySeeder: Inserted {$name}\n";
        } else {
            echo "CompanySeeder: {$name} already exists\n";
        }
    }
}
