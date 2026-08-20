<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBillingItemsTable extends Migration
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
            'billing_type' => [
                'type'       => 'ENUM',
                'constraint' => ['ipl', 'water', 'electricity', 'other'],
                'default'    => 'ipl',
            ],
            'billing_period_start' => [
                'type' => 'DATE',
            ],
            'billing_period_end' => [
                'type' => 'DATE',
            ],
            'meter_reading_id' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'description' => [
                'type' => 'TEXT',
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 1.00,
            ],
            'unit' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'ls',
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'management_fee_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
                'default'    => null,
            ],
            'management_fee_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'null'       => true,
                'default'    => null,
            ],
            'pln_charge' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'null'       => true,
                'default'    => null,
            ],
            'apply_tax' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'invoiced', 'cancelled'],
                'default'    => 'draft',
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
        $this->forge->addKey('billing_type');
        $this->forge->addKey('billing_period_start');
        $this->forge->addKey('billing_period_end');
        $this->forge->addKey('meter_reading_id');
        $this->forge->addKey('status');

        $this->forge->createTable('billing_items');
    }

    public function down()
    {
        $this->forge->dropTable('billing_items');
    }
}
