<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOwnershipsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'project_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'cluster_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'block_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'lot_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'billing_address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'area' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'ipl_rate_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'water_rate_group_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'ownership_type' => [
                'type'       => 'ENUM',
                'constraint' => ['residential', 'commercial'],
            ],
            'start_date' => [
                'type' => 'DATE',
            ],
            'end_date' => [
                'type'    => 'DATE',
                'null'    => true,
                'default' => null,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('project_id');
        $this->forge->addKey('ipl_rate_id');

        $this->forge->createTable('ownerships');
    }

    public function down()
    {
        $this->forge->dropTable('ownerships');
    }
}
