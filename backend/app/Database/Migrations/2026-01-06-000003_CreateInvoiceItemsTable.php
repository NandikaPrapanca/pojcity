<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Invoice Items Table
 *
 * Immutable snapshot of billing_items at the moment an invoice is generated.
 * Once written, these rows must never be modified or soft-deleted, ensuring
 * the invoice is a permanent legal record even if the original billing_items
 * are later edited or cancelled.
 *
 * No soft deletes — intentional.
 */
class CreateInvoiceItemsTable extends Migration
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
            // Reference to the source billing_item (for traceability only)
            'billing_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            // --- Snapshot fields (copy of billing_items values at generation time) ---
            'billing_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'billing_period_start' => [
                'type' => 'DATE',
            ],
            'billing_period_end' => [
                'type' => 'DATE',
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
            // --- Timestamps (insert only, no update) ---
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('billing_item_id');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('invoice_items');
    }

    public function down()
    {
        $this->forge->dropTable('invoice_items');
    }
}
