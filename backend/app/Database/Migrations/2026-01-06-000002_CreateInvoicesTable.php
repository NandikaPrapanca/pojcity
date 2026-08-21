<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Invoices Table
 *
 * Main invoice header table. Each row represents one invoice issued to a customer
 * for one or more billing items belonging to a single ownership.
 *
 * Tax math (Indonesian DPP Nilai Lain scheme for 12% PPN):
 *   DPP Nilai Lain  = (11/12) × subtotal_dpp
 *   PPN             = 12%    × DPP Nilai Lain
 *   Grand Total     = subtotal_dpp + PPN
 */
class CreateInvoicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // e.g. "INV/2026/08/0001"
            'invoice_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'company_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'ownership_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            // The user who triggered generation
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'issue_date' => [
                'type' => 'DATE',
            ],
            'due_date' => [
                'type' => 'DATE',
            ],
            // Sum of all billing item subtotals (base for tax)
            'subtotal_dpp' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            // (11/12) × subtotal_dpp  — the DPP Nilai Lain base
            'dpp_nilai_lain' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            // 12% × dpp_nilai_lain
            'ppn_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            // subtotal_dpp + ppn_amount
            'grand_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            // Whether PPN was applied (false = items all have apply_tax=0)
            'tax_applied' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            // The PPN rate snapshot at time of generation (e.g. 12.00)
            'ppn_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 12.00,
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'sent', 'paid', 'overdue', 'cancelled'],
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
        $this->forge->addUniqueKey('invoice_number');
        $this->forge->addKey('company_id');
        $this->forge->addKey('ownership_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('created_by');
        $this->forge->addKey('issue_date');
        $this->forge->addKey('due_date');
        $this->forge->addKey('status');

        $this->forge->createTable('invoices');
    }

    public function down()
    {
        $this->forge->dropTable('invoices');
    }
}
