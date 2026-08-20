<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBillingItemTiersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'billing_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'tier_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'usage_in_tier' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('billing_item_id');

        $this->forge->createTable('billing_item_tiers');
    }

    public function down()
    {
        $this->forge->dropTable('billing_item_tiers');
    }
}
