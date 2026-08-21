<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * WhatsApp Logs Table
 *
 * Records every WhatsApp delivery attempt (real or mock) for audit purposes.
 * For the prototype, all rows will have status = 'simulated'.
 * When integrated with a real provider (e.g. Fonnte), status can be
 * 'sent', 'failed', or 'delivered'.
 */
class CreateWhatsappLogsTable extends Migration
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
            // Destination phone number — stored as-is from ownership/customer record
            'customer_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'default'    => null,
            ],
            // Full formatted message body that was (or would be) sent
            'message_body' => [
                'type' => 'TEXT',
            ],
            // 'simulated' | 'sent' | 'failed' | 'delivered'
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['simulated', 'sent', 'failed', 'delivered'],
                'default'    => 'simulated',
            ],
            // Optional error detail when status = 'failed'
            'error_message' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('whatsapp_logs');
    }

    public function down()
    {
        $this->forge->dropTable('whatsapp_logs');
    }
}
