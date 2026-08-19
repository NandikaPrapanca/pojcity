<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateIplRatesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'project_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'rate_per_sqm' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'effective_date' => [
                'type' => 'DATE',
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
        $this->forge->addKey('project_id');
        $this->forge->createTable('ipl_rates');
    }

    public function down()
    {
        $this->forge->dropTable('ipl_rates');
    }
}
