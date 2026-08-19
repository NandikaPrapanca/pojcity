<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMeterReadingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'ownership_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'meter_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'reading_date' => [
                'type' => 'DATE',
            ],
            'previous_reading' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'current_reading' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'usage' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'photo_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'billing_period_start' => [
                'type' => 'DATE',
            ],
            'billing_period_end' => [
                'type' => 'DATE',
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
        $this->forge->addKey('ownership_id');
        $this->forge->addKey('reading_date');

        $this->forge->createTable('meter_readings');
    }

    public function down()
    {
        $this->forge->dropTable('meter_readings');
    }
}
