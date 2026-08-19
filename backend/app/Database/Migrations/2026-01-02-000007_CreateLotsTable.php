<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLotsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'block_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'lot_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'area' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
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
        $this->forge->addKey('block_id');
        $this->forge->createTable('lots');
    }

    public function down()
    {
        $this->forge->dropTable('lots');
    }
}
