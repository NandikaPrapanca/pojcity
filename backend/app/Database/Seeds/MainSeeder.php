<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run()
    {
        $this->call('RolesSeeder');
        $this->call('UsersSeeder');
        $this->call('CompanySeeder');
        $this->call('Phase2Seeder');
    }
}
