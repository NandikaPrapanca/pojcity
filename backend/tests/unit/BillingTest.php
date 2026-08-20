<?php

namespace App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\BillingService;
use App\Models\BillingItemModel;

class BillingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected BillingService  $service;
    protected BillingItemModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingService();
        $this->model   = new BillingItemModel();

        $db = \Config\Database::connect();
        $db->table('billing_item_tiers')->emptyTable();
        $db->table('billing_items')->emptyTable();
        $db->table('meter_readings')->emptyTable();
        $db->table('ownerships')->emptyTable();
        $db->table('lots')->emptyTable();
        $db->table('blocks')->emptyTable();
        $db->table('clusters')->emptyTable();
        $db->table('ipl_rates')->emptyTable();
        $db->table('projects')->emptyTable();
        $db->table('customers')->emptyTable();
        $db->table('companies')->emptyTable();
    }

    private function createSeedOwnership(): int
    {
        $db = \Config\Database::connect();

        // 1. Company
        $db->table('companies')->insert([
            'name'       => 'PT IPU Land',
            'phone'      => '024-1234567',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $companyId = $db->insertID();

        // 2. Customer
        $db->table('customers')->insert([
            'company_id'      => $companyId,
            'name'            => 'Budi Santoso',
            'customer_type'   => 'individual',
            'whatsapp'        => '08123456789',
            'billing_address' => 'Ibiza Blok B No. 5',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $customerId = $db->insertID();

        // 3. Project
        $db->table('projects')->insert([
            'company_id'   => $companyId,
            'name'         => 'Ibiza Residential',
            'project_type' => 'residential',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $projectId = $db->insertID();

        // 4. Cluster
        $db->table('clusters')->insert([
            'project_id' => $projectId,
            'name'       => 'Cluster Costa',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $clusterId = $db->insertID();

        // 5. Block
        $db->table('blocks')->insert([
            'cluster_id' => $clusterId,
            'name'       => 'Blok B',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $blockId = $db->insertID();

        // 6. Lot
        $db->table('lots')->insert([
            'block_id'   => $blockId,
            'lot_number' => '05',
            'area'       => 180.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lotId = $db->insertID();

        // 7. IPL Rate
        $db->table('ipl_rates')->insert([
            'project_id'     => $projectId,
            'name'           => 'Standard Ibiza',
            'rate_per_sqm'   => 4500.00,
            'effective_date' => '2026-01-01',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $iplRateId = $db->insertID();

        // 8. Ownership
        $db->table('ownerships')->insert([
            'customer_id'     => $customerId,
            'project_id'      => $projectId,
            'cluster_id'      => $clusterId,
            'block_id'        => $blockId,
            'lot_id'          => $lotId,
            'billing_address' => 'Ibiza Blok B No. 05',
            'area'            => 180.00,
            'ipl_rate_id'     => $iplRateId,
            'ownership_type'  => 'residential',
            'start_date'      => '2026-01-01',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    /**
     * 1. Test creating valid billing item and verifying decimal-safe subtotal.
     * Realistic example: IPL residential Ibiza, period 2026-07-01 to 2026-08-01, qty 180, price 4,500 -> 810,000.
     */
    public function testCreateValidBillingItemAndCalculation()
    {
        $ownershipId = $this->createSeedOwnership();

        $payload = [
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'ipl',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'Iuran Pengelolaan Lingkungan (IPL) Juli 2026',
            'quantity'             => '180.00',
            'unit'                 => 'm²',
            'unit_price'           => '4500.00',
            'apply_tax'            => 1,
            'notes'                => 'Tagihan reguler',
        ];

        $res = $this->service->create($payload);

        $this->assertTrue($res['success']);
        $this->assertNotNull($res['data']);
        $this->assertEquals(810000.00, (float) $res['data']['subtotal']);
        $this->assertEquals('ipl', $res['data']['billing_type']);
        $this->assertEquals('draft', $res['data']['status']);
        $this->assertEquals('Budi Santoso', $res['data']['customer_name']);
        $this->assertEquals('Ibiza Residential', $res['data']['project_name']);
    }

    /**
     * 2. Test Get billing item by ID and relations enrichment.
     */
    public function testGetBillingItemById()
    {
        $ownershipId = $this->createSeedOwnership();

        $createRes = $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'water',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'Pemakaian Air Juli 2026',
            'quantity'             => '25.00',
            'unit'                 => 'm³',
            'unit_price'           => '8000.00',
        ]);

        $itemId = (int) $createRes['data']['id'];

        $item = $this->service->getById($itemId);
        $this->assertNotNull($item);
        $this->assertEquals($itemId, (int) $item['id']);
        $this->assertEquals(200000.00, (float) $item['subtotal']);
    }

    /**
     * 3. Test Update billing item.
     */
    public function testUpdateBillingItem()
    {
        $ownershipId = $this->createSeedOwnership();

        $createRes = $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'other',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'Biaya Kebersihan Tambahan',
            'quantity'             => '1.00',
            'unit'                 => 'ls',
            'unit_price'           => '50000.00',
        ]);

        $itemId = (int) $createRes['data']['id'];

        $updateRes = $this->service->update($itemId, [
            'description' => 'Biaya Kebersihan & Keamanan Tambahan',
            'unit_price'  => '75000.00',
        ]);

        $this->assertTrue($updateRes['success']);
        $this->assertEquals('Biaya Kebersihan & Keamanan Tambahan', $updateRes['data']['description']);
        $this->assertEquals(75000.00, (float) $updateRes['data']['subtotal']);
    }

    /**
     * 4. Test Delete / Soft delete billing item and historical preservation.
     */
    public function testDeleteAndSoftDeleteBehavior()
    {
        $ownershipId = $this->createSeedOwnership();

        $createRes = $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'electricity',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'Biaya Listrik',
            'quantity'             => '1.00',
            'unit'                 => 'ls',
            'unit_price'           => '150000.00',
        ]);

        $itemId = (int) $createRes['data']['id'];

        $delRes = $this->service->delete($itemId);
        $this->assertTrue($delRes['success']);

        // Service getById should return null for active query
        $this->assertNull($this->service->getById($itemId));

        // Direct DB query reveals record is soft-deleted (deleted_at IS NOT NULL)
        $db = \Config\Database::connect();
        $row = $db->table('billing_items')->where('id', $itemId)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertNotNull($row['deleted_at']);
    }

    /**
     * 5, 6, 7. Test Filtering by ownership, billing_type, and billing period.
     */
    public function testFilters()
    {
        $ownershipId = $this->createSeedOwnership();

        $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'ipl',
            'billing_period_start' => '2026-05-01',
            'billing_period_end'   => '2026-06-01',
            'description'          => 'IPL Mei 2026',
            'quantity'             => '180.00',
            'unit_price'           => '4500.00',
        ]);

        $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'water',
            'billing_period_start' => '2026-06-01',
            'billing_period_end'   => '2026-07-01',
            'description'          => 'Air Juni 2026',
            'quantity'             => '20.00',
            'unit_price'           => '7500.00',
        ]);

        // Filter by ownership
        $byOwnership = $this->service->getByOwnership($ownershipId);
        $this->assertCount(2, $byOwnership);

        // Filter by billing type
        $byType = $this->service->getAll(['billing_type' => 'ipl']);
        $this->assertCount(1, $byType);
        $this->assertEquals('ipl', $byType[0]['billing_type']);

        // Filter by period
        $byPeriod = $this->service->getAll([
            'billing_period_start' => '2026-06-01',
            'billing_period_end'   => '2026-07-01',
        ]);
        $this->assertCount(1, $byPeriod);
        $this->assertEquals('water', $byPeriod[0]['billing_type']);
    }

    /**
     * 8. Reject invalid ownership.
     */
    public function testRejectInvalidOwnership()
    {
        $res = $this->service->create([
            'ownership_id'         => 999999, // Non-existent
            'billing_type'         => 'ipl',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'IPL Test',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Kepemilikan yang dipilih tidak valid', $res['error']);
    }

    /**
     * 9. Reject invalid billing period (end <= start).
     */
    public function testRejectInvalidBillingPeriod()
    {
        $ownershipId = $this->createSeedOwnership();

        $res = $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'ipl',
            'billing_period_start' => '2026-08-01',
            'billing_period_end'   => '2026-07-01', // End date is before start date
            'description'          => 'IPL Test',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Tanggal akhir periode harus lebih besar', $res['error']);
    }

    /**
     * 10. Reject duplicate billing for same ownership, billing type, and period.
     */
    public function testRejectDuplicateBilling()
    {
        $ownershipId = $this->createSeedOwnership();

        // 1st insertion succeeds
        $first = $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'ipl',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'IPL Juli 2026',
            'quantity'             => '180.00',
            'unit_price'           => '4500.00',
        ]);
        $this->assertTrue($first['success']);

        // 2nd insertion with exact same ownership, type, and period range must be rejected
        $duplicate = $this->service->create([
            'ownership_id'         => $ownershipId,
            'billing_type'         => 'ipl',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
            'description'          => 'IPL Juli 2026 (Duplikat)',
            'quantity'             => '180.00',
            'unit_price'           => '4500.00',
        ]);

        $this->assertFalse($duplicate['success']);
        $this->assertStringContainsString('sudah ada', $duplicate['error']);
    }
}
