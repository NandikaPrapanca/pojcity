<?php

namespace App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\BillingService;
use App\Models\BillingItemModel;

/**
 * Phase 5B — IPL Billing Engine Tests
 *
 * Test scenarios as specified in the Phase 5B requirements:
 *  T01 valid residential IPL:   180 × 4500 = 810,000
 *  T02 valid commercial IPL:    500 × 8000 = 4,000,000
 *  T03 special IPL rate:        ownership-specific configured rate is used (50% = 2,250/m²)
 *  T04 invalid ownership:       must reject
 *  T05 missing IPL rate:        must reject
 *  T06 area <= 0:               must reject
 *  T07 invalid billing period:  must reject
 *  T08 duplicate IPL:           must reject
 *  T09 historical snapshot:     generate → change rate → verify existing item unchanged
 *  T10 decimal/precision:       decimal area and rate, verify accuracy
 *  T11 soft-deleted ownership:  must reject
 *  T12 wrong/deleted IPL rate:  must reject
 */
class IplBillingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected BillingService  $service;
    protected BillingItemModel $model;

    // ─── Shared IDs created in helpers ────────────────────────────────────────
    private int $companyId;
    private int $customerId;
    private int $projectId;
    private int $clusterId;
    private int $blockId;
    private int $lotId;
    private int $standardRateId;   // 4500/m²
    private int $halfRateId;       // 2250/m² (50% special)
    private int $commercialRateId; // 8000/m²

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

        $this->seedMasterData();
    }

    // ─── Seed helpers ──────────────────────────────────────────────────────────

    private function seedMasterData(): void
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        // Company
        $db->table('companies')->insert([
            'name' => 'PT IPU Land', 'phone' => '024-1234567',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->companyId = (int) $db->insertID();

        // Customer
        $db->table('customers')->insert([
            'company_id' => $this->companyId, 'name' => 'Budi Santoso',
            'customer_type' => 'individual', 'whatsapp' => '08123456789',
            'billing_address' => 'Ibiza Blok B No.5',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->customerId = (int) $db->insertID();

        // Project
        $db->table('projects')->insert([
            'company_id' => $this->companyId, 'name' => 'Ibiza',
            'project_type' => 'residential',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->projectId = (int) $db->insertID();

        // Cluster, Block, Lot (residential hierarchy)
        $db->table('clusters')->insert([
            'project_id' => $this->projectId, 'name' => 'Cluster A',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->clusterId = (int) $db->insertID();

        $db->table('blocks')->insert([
            'cluster_id' => $this->clusterId, 'name' => 'Blok B',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->blockId = (int) $db->insertID();

        $db->table('lots')->insert([
            'block_id' => $this->blockId, 'lot_number' => '05', 'area' => 180.00,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->lotId = (int) $db->insertID();

        // IPL Rates
        $db->table('ipl_rates')->insert([
            'project_id' => $this->projectId, 'name' => 'Standard Ibiza',
            'rate_per_sqm' => 4500.00, 'effective_date' => '2026-01-01',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->standardRateId = (int) $db->insertID();

        $db->table('ipl_rates')->insert([
            'project_id' => $this->projectId, 'name' => 'Khusus 50% Ibiza',
            'rate_per_sqm' => 2250.00, 'effective_date' => '2026-01-01',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->halfRateId = (int) $db->insertID();

        $db->table('ipl_rates')->insert([
            'project_id' => $this->projectId, 'name' => 'Standard Mall 23',
            'rate_per_sqm' => 8000.00, 'effective_date' => '2026-01-01',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->commercialRateId = (int) $db->insertID();
    }

    /**
     * Create a residential ownership linked to given IPL rate.
     */
    private function createResidentialOwnership(int $iplRateId, float $area = 180.00): int
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('ownerships')->insert([
            'customer_id'    => $this->customerId,
            'project_id'     => $this->projectId,
            'cluster_id'     => $this->clusterId,
            'block_id'       => $this->blockId,
            'lot_id'         => $this->lotId,
            'billing_address'=> 'Ibiza Blok B No.05',
            'area'           => $area,
            'ipl_rate_id'    => $iplRateId,
            'ownership_type' => 'residential',
            'start_date'     => '2026-01-01',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        return (int) $db->insertID();
    }

    /**
     * Create a commercial ownership.
     */
    private function createCommercialOwnership(int $iplRateId, float $area = 500.00): int
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('ownerships')->insert([
            'customer_id'    => $this->customerId,
            'project_id'     => $this->projectId,
            'billing_address'=> 'Lantai 3 Mall 23',
            'area'           => $area,
            'ipl_rate_id'    => $iplRateId,
            'ownership_type' => 'commercial',
            'start_date'     => '2026-01-01',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        return (int) $db->insertID();
    }

    // ─── T01: Valid residential IPL ───────────────────────────────────────────

    public function testT01ValidResidentialIpl()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId, 180.00);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $item = $result['data'];
        $this->assertEquals(180.00, (float) $item['quantity']);
        $this->assertEquals(4500.00, (float) $item['unit_price']);
        $this->assertEquals(810000.00, (float) $item['subtotal']); // 180 × 4500 = 810,000
        $this->assertEquals('ipl', $item['billing_type']);
        $this->assertEquals('m²', $item['unit']);
        $this->assertEquals('draft', $item['status']);
        $this->assertEquals(1, (int) $item['apply_tax']);
        $this->assertEquals('Budi Santoso', $item['customer_name']);
        $this->assertEquals('Ibiza', $item['project_name']);
    }

    // ─── T02: Valid commercial IPL ────────────────────────────────────────────

    public function testT02ValidCommercialIpl()
    {
        $ownershipId = $this->createCommercialOwnership($this->commercialRateId, 500.00);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertEquals(500.00, (float) $result['data']['quantity']);
        $this->assertEquals(8000.00, (float) $result['data']['unit_price']);
        $this->assertEquals(4000000.00, (float) $result['data']['subtotal']); // 500 × 8000 = 4,000,000
    }

    // ─── T03: Special IPL rate (ownership-specific) ───────────────────────────

    public function testT03SpecialIplRate()
    {
        // Use 50% special rate: 2250/m²
        $ownershipId = $this->createResidentialOwnership($this->halfRateId, 340.00);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        // 340 × 2250 = 765,000
        $this->assertEquals(2250.00, (float) $result['data']['unit_price']);
        $this->assertEquals(765000.00, (float) $result['data']['subtotal']);
        // Verify the rate name is the special one (indirect check via ownership_id-based rate)
        $this->assertEquals(340.00, (float) $result['data']['quantity']);
    }

    // ─── T04: Invalid ownership rejected ──────────────────────────────────────

    public function testT04InvalidOwnershipRejected()
    {
        $result = $this->service->generateIpl([
            'ownership_id'         => 999999,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Kepemilikan yang dipilih tidak valid', $result['error']);
    }

    // ─── T05: Missing IPL rate rejected ───────────────────────────────────────

    public function testT05MissingIplRateRejected()
    {
        // Create ownership without ipl_rate_id
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('ownerships')->insert([
            'customer_id'    => $this->customerId,
            'project_id'     => $this->projectId,
            'billing_address'=> 'Test no-rate address',
            'area'           => 150.00,
            'ipl_rate_id'    => null,
            'ownership_type' => 'commercial',
            'start_date'     => '2026-01-01',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $ownershipId = (int) $db->insertID();

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('belum memiliki tarif IPL', $result['error']);
    }

    // ─── T06: Area <= 0 rejected ──────────────────────────────────────────────

    public function testT06ZeroAreaRejected()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId, 0.00);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Luas area', $result['error']);
    }

    // ─── T07: Invalid billing period rejected ─────────────────────────────────

    public function testT07InvalidBillingPeriodRejected()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId);

        // End date before start date
        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-08-01',
            'billing_period_end'   => '2026-07-01',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Tanggal akhir periode harus lebih besar', $result['error']);

        // Equal dates also invalid
        $result2 = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-07-01',
        ]);
        $this->assertFalse($result2['success']);
    }

    // ─── T08: Duplicate IPL generation rejected ───────────────────────────────

    public function testT08DuplicateIplRejected()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId);

        // First generation: OK
        $first = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);
        $this->assertTrue($first['success']);

        // Second generation: same ownership + ipl + period → must reject
        $second = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('sudah ada', $second['error']);
    }

    // ─── T09: Historical snapshot — rate change does NOT affect existing item ─

    public function testT09HistoricalSnapshotPreserved()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId, 180.00);

        // Generate IPL billing item (snapshots rate 4500/m²)
        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);
        $this->assertTrue($result['success']);
        $itemId = (int) $result['data']['id'];

        $originalUnitPrice = (float) $result['data']['unit_price'];
        $originalSubtotal  = (float) $result['data']['subtotal'];
        $this->assertEquals(4500.00, $originalUnitPrice);
        $this->assertEquals(810000.00, $originalSubtotal);

        // Simulate: ownership changes to 50% special rate
        $db = \Config\Database::connect();
        $db->table('ownerships')->update(['ipl_rate_id' => $this->halfRateId], ['id' => $ownershipId]);

        // Fetch the ORIGINAL billing item — must NOT change
        $existing = $this->service->getById($itemId);
        $this->assertNotNull($existing);
        $this->assertEquals(4500.00, (float) $existing['unit_price'], 'Snapshot unit_price must not change when ownership rate changes');
        $this->assertEquals(810000.00, (float) $existing['subtotal'], 'Snapshot subtotal must not change when ownership rate changes');

        // New generation with new rate succeeds (different period to avoid duplicate)
        $newResult = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-08-01',
            'billing_period_end'   => '2026-09-01',
        ]);
        $this->assertTrue($newResult['success']);
        $this->assertEquals(2250.00, (float) $newResult['data']['unit_price'], 'New billing must use updated rate');
        $this->assertEquals(405000.00, (float) $newResult['data']['subtotal']); // 180 × 2250 = 405,000
    }

    // ─── T10: Decimal precision ───────────────────────────────────────────────

    public function testT10DecimalPrecision()
    {
        // Create rate with decimal rate: 4500.50/m²
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('ipl_rates')->insert([
            'project_id'   => $this->projectId,
            'name'         => 'Decimal Rate',
            'rate_per_sqm' => 4500.50,
            'effective_date' => '2026-01-01',
            'created_at'   => $now, 'updated_at' => $now,
        ]);
        $decRateId = (int) $db->insertID();

        // Create ownership with decimal area: 123.45 m²
        $ownershipId = $this->createResidentialOwnership($decRateId, 123.45);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        // Expected: round(123.45 × 4500.50, 2) = round(555,836.725, 2) = 555,836.73
        $expected = round(123.45 * 4500.50, 2);
        $actual   = (float) $result['data']['subtotal'];
        $this->assertEqualsWithDelta($expected, $actual, 0.01, "Decimal subtotal must be accurate to 2 decimal places");
    }

    // ─── T11: Soft-deleted ownership rejected ─────────────────────────────────

    public function testT11SoftDeletedOwnershipRejected()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId);

        // Soft-delete the ownership
        $db = \Config\Database::connect();
        $db->table('ownerships')->update(['deleted_at' => date('Y-m-d H:i:s')], ['id' => $ownershipId]);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($result['success']);
        // Model's find() respects soft deletes, so it returns null → "tidak valid"
        $this->assertStringContainsString('Kepemilikan yang dipilih tidak valid', $result['error']);
    }

    // ─── T12: Soft-deleted/inactive IPL rate rejected ─────────────────────────

    public function testT12SoftDeletedIplRateRejected()
    {
        $ownershipId = $this->createResidentialOwnership($this->standardRateId);

        // Soft-delete the IPL rate
        $db = \Config\Database::connect();
        $db->table('ipl_rates')->update(['deleted_at' => date('Y-m-d H:i:s')], ['id' => $this->standardRateId]);

        $result = $this->service->generateIpl([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($result['success']);
        // IplRateModel uses soft deletes so find() returns null → "tidak valid atau telah dihapus"
        $this->assertStringContainsString('tidak valid', $result['error']);
    }
}
