<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWaterRateTiersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'water_rate_group_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'min_usage' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'max_usage' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'rate_per_m3' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('water_rate_group_id');
        $this->forge->createTable('water_rate_tiers');
    }

    public function down()
    {
        $this->forge->dropTable('water_rate_tiers');
    }
}
