<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaxConfigurationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'dpp_multiplier_numerator' => [
                'type'    => 'INT',
                'default' => 11,
            ],
            'dpp_multiplier_denominator' => [
                'type'    => 'INT',
                'default' => 12,
            ],
            'ppn_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'default'    => 0.1200,
            ],
            'effective_date' => [
                'type' => 'DATE',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
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
        $this->forge->createTable('tax_configurations');
    }

    public function down()
    {
        $this->forge->dropTable('tax_configurations');
    }
}
