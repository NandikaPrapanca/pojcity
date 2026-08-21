<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * System Settings Table
 *
 * A generic key/value store for application-wide configuration.
 * Used for:
 *  - invoice_sequence_{YYYY_MM}: current invoice sequence counter per month
 *  - invoice_due_date_offset_days: how many days after issue date the invoice is due
 */
class CreateSystemSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'description' => [
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
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('key');

        $this->forge->createTable('system_settings');

        // Seed default settings
        $this->db->table('system_settings')->insertBatch([
            [
                'key'         => 'invoice_due_date_offset_days',
                'value'       => '14',
                'description' => 'Number of days after invoice issue date before it is due.',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('system_settings');
    }
}
