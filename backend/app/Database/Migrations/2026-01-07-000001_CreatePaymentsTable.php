<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Payments Table
 *
 * Records payment transactions against issued invoices.
 * Upon successful payment recording, the linked invoice status is updated to 'paid'.
 */
class CreatePaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'invoice_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'Transfer BCA',
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'reference_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
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
        $this->forge->addKey('invoice_id');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('payments');
    }

    public function down()
    {
        $this->forge->dropTable('payments');
    }
}
