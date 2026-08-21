<?php

namespace App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\BillingService;
use App\Models\BillingItemModel;
use App\Models\BillingItemTierModel;

/**
 * Phase 5C Water Billing Engine Tests
 * Tiers: 0-20 @7500, 20-40 @8500, 40-inf @9500 | Abonemen: 45000
 */
class WaterBillingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected BillingService    $service;
    protected BillingItemModel  $model;
    protected BillingItemTierModel $tierModel;

    private int $companyId;
    private int $customerId;
    private int $projectId;
    private int $clusterId;
    private int $blockId;
    private int $lotId;
    private int $waterGroupId;
    private int $tier1Id;
    private int $tier2Id;
    private int $tier3Id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service   = new BillingService();
        $this->model     = new BillingItemModel();
        $this->tierModel = new BillingItemTierModel();

        $db = \Config\Database::connect();
        $db->table('billing_item_tiers')->emptyTable();
        $db->table('billing_items')->emptyTable();
        $db->table('meter_readings')->emptyTable();
        $db->table('ownerships')->emptyTable();
        $db->table('lots')->emptyTable();
        $db->table('blocks')->emptyTable();
        $db->table('clusters')->emptyTable();
        $db->table('water_rate_tiers')->emptyTable();
        $db->table('water_rate_groups')->emptyTable();
        $db->table('ipl_rates')->emptyTable();
        $db->table('projects')->emptyTable();
        $db->table('customers')->emptyTable();
        $db->table('companies')->emptyTable();

        $this->seedMasterData();
    }

    private function seedMasterData(): void
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $db->table('companies')->insert(['name' => 'PT IPU Land', 'phone' => '024-111', 'created_at' => $now, 'updated_at' => $now]);
        $this->companyId = (int) $db->insertID();

        $db->table('customers')->insert(['company_id' => $this->companyId, 'name' => 'Budi Santoso', 'customer_type' => 'individual', 'whatsapp' => '08123456789', 'billing_address' => 'Ibiza B5', 'created_at' => $now, 'updated_at' => $now]);
        $this->customerId = (int) $db->insertID();

        $db->table('projects')->insert(['company_id' => $this->companyId, 'name' => 'Ibiza Residence', 'project_type' => 'residential', 'created_at' => $now, 'updated_at' => $now]);
        $this->projectId = (int) $db->insertID();

        $db->table('clusters')->insert(['project_id' => $this->projectId, 'name' => 'Cluster A', 'created_at' => $now, 'updated_at' => $now]);
        $this->clusterId = (int) $db->insertID();

        $db->table('blocks')->insert(['cluster_id' => $this->clusterId, 'name' => 'Blok B', 'created_at' => $now, 'updated_at' => $now]);
        $this->blockId = (int) $db->insertID();

        $db->table('lots')->insert(['block_id' => $this->blockId, 'lot_number' => '05', 'area' => 180.00, 'created_at' => $now, 'updated_at' => $now]);
        $this->lotId = (int) $db->insertID();

        $db->table('water_rate_groups')->insert(['project_id' => $this->projectId, 'name' => 'Tarif Air Ibiza', 'abonemen' => 45000.00, 'created_at' => $now, 'updated_at' => $now]);
        $this->waterGroupId = (int) $db->insertID();

        $db->table('water_rate_tiers')->insert(['water_rate_group_id' => $this->waterGroupId, 'min_usage' => 0.00, 'max_usage' => 20.00, 'rate_per_m3' => 7500.00, 'created_at' => $now, 'updated_at' => $now]);
        $this->tier1Id = (int) $db->insertID();

        $db->table('water_rate_tiers')->insert(['water_rate_group_id' => $this->waterGroupId, 'min_usage' => 20.00, 'max_usage' => 40.00, 'rate_per_m3' => 8500.00, 'created_at' => $now, 'updated_at' => $now]);
        $this->tier2Id = (int) $db->insertID();

        $db->table('water_rate_tiers')->insert(['water_rate_group_id' => $this->waterGroupId, 'min_usage' => 40.00, 'max_usage' => null, 'rate_per_m3' => 9500.00, 'created_at' => $now, 'updated_at' => $now]);
        $this->tier3Id = (int) $db->insertID();
    }

    private function createOwnership(?int $waterGroupId = null): int
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('ownerships')->insert(['customer_id' => $this->customerId, 'project_id' => $this->projectId, 'cluster_id' => $this->clusterId, 'block_id' => $this->blockId, 'lot_id' => $this->lotId, 'billing_address' => 'Ibiza B5', 'area' => 180.00, 'water_rate_group_id' => $waterGroupId, 'ownership_type' => 'residential', 'start_date' => '2026-01-01', 'created_at' => $now, 'updated_at' => $now]);
        return (int) $db->insertID();
    }

    private function insertMeterReading(int $ownershipId, float $prev, float $curr, string $readingDate = '2026-07-28'): int
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('meter_readings')->insert(['ownership_id' => $ownershipId, 'meter_number' => 'MTR-TEST', 'reading_date' => $readingDate, 'previous_reading' => $prev, 'current_reading' => $curr, 'usage' => round($curr - $prev, 2), 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-07-31', 'created_at' => $now, 'updated_at' => $now]);
        return (int) $db->insertID();
    }

    public function testT01ExactlyAtFirstBoundary()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 120.00);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertEquals(20.00, (float) $result['data']['quantity']);
        $this->assertEquals(195000.00, (float) $result['data']['subtotal']); // 20x7500 + 45000
        $this->assertEquals('water', $result['data']['billing_type']);
        $this->assertEquals('draft', $result['data']['status']);

        $tiers = $this->tierModel->getForBillingItem((int) $result['data']['id']);
        $this->assertCount(2, $tiers);
        $this->assertEquals(150000.00, (float) $tiers[0]['amount']);
        $this->assertEquals(45000.00, (float) $tiers[1]['amount']);
    }

    public function testT02CrossesIntoSecondTier()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 120.01);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $expected = round(20.00 * 7500 + 0.01 * 8500 + 45000, 2);
        $this->assertEquals(20.01, (float) $result['data']['quantity']);
        $this->assertEqualsWithDelta($expected, (float) $result['data']['subtotal'], 0.01);

        $tiers = $this->tierModel->getForBillingItem((int) $result['data']['id']);
        $this->assertCount(3, $tiers);
    }

    public function testT03ProgressiveCalculation2458()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 124.58);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertEquals(24.58, (float) $result['data']['quantity']);
        $this->assertEquals(233930.00, (float) $result['data']['subtotal']);

        $tiers = $this->tierModel->getForBillingItem((int) $result['data']['id']);
        $this->assertCount(3, $tiers);
        $this->assertEquals(20.00, (float) $tiers[0]['usage_in_tier']);
        $this->assertEquals(150000.00, (float) $tiers[0]['amount']);
        $this->assertEquals(4.58, (float) $tiers[1]['usage_in_tier']);
        $this->assertEqualsWithDelta(38930.00, (float) $tiers[1]['amount'], 0.01);
    }

    public function testT04ExactlyFortyM3()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 140.00);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertEquals(40.00, (float) $result['data']['quantity']);
        $this->assertEquals(365000.00, (float) $result['data']['subtotal']);
    }

    public function testT05CrossesIntoThirdTier()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 140.01);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $expected = round(20 * 7500 + 20 * 8500 + 0.01 * 9500 + 45000, 2);
        $this->assertEqualsWithDelta($expected, (float) $result['data']['subtotal'], 0.01);

        $tiers = $this->tierModel->getForBillingItem((int) $result['data']['id']);
        $this->assertCount(4, $tiers);
    }

    public function testT06AboveFinalTier()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 160.00);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertEquals(60.00, (float) $result['data']['quantity']);
        $this->assertEquals(555000.00, (float) $result['data']['subtotal']);

        $tiers = $this->tierModel->getForBillingItem((int) $result['data']['id']);
        $this->assertCount(4, $tiers);
    }

    public function testT07DecimalUsagePrecision()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 200.00, 215.73);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $expected = round(15.73 * 7500 + 45000, 2);
        $this->assertEquals(15.73, (float) $result['data']['quantity']);
        $this->assertEqualsWithDelta($expected, (float) $result['data']['subtotal'], 0.01);
    }

    public function testT08AbonemenIncluded()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 110.00);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertEquals(120000.00, (float) $result['data']['subtotal']); // 10x7500+45000

        $tiers = $this->tierModel->getForBillingItem((int) $result['data']['id']);
        $abonemenTiers = array_values(array_filter($tiers, fn($t) => $t['tier_label'] === 'Abonemen'));
        $this->assertNotEmpty($abonemenTiers);
        $this->assertEquals(45000.00, (float) $abonemenTiers[0]['amount']);
    }

    public function testT09NoWaterRateGroupRejected()
    {
        $ownershipId = $this->createOwnership(null);
        $this->insertMeterReading($ownershipId, 100.00, 124.58);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Kelompok Tarif Air', $result['error']);
    }

    public function testT10NoMeterReadingRejected()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('meter reading', $result['error']);
    }

    public function testT11InvalidOwnershipRejected()
    {
        $result = $this->service->generateWater(['ownership_id' => 999999, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak valid', $result['error']);
    }

    public function testT12InvalidBillingPeriodRejected()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-08-01', 'billing_period_end' => '2026-07-01']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Tanggal akhir periode harus lebih besar', $result['error']);
    }

    public function testT13DuplicateWaterBillingRejected()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 124.58);

        $first = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);
        $this->assertTrue($first['success']);

        $second = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('sudah ada', $second['error']);
    }

    public function testT14HistoricalSnapshotPreserved()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $this->insertMeterReading($ownershipId, 100.00, 124.58);

        $first = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);
        $this->assertTrue($first['success']);
        $itemId = (int) $first['data']['id'];
        $this->assertEquals(233930.00, (float) $first['data']['subtotal']);

        // Change tier1 rate — existing item must stay frozen
        $db = \Config\Database::connect();
        $db->table('water_rate_tiers')->update(['rate_per_m3' => 10000.00], ['id' => $this->tier1Id]);

        $existing = $this->service->getById($itemId);
        $this->assertEquals(233930.00, (float) $existing['subtotal'], 'Snapshot must be frozen');

        $tiers = $this->tierModel->getForBillingItem($itemId);
        $tier1 = array_values(array_filter($tiers, fn($t) => str_contains($t['tier_label'], '7.500')));
        $this->assertNotEmpty($tier1, 'Original rate 7500 must be in snapshot');

        // New billing with different period uses updated rate
        $this->insertMeterReading($ownershipId, 124.58, 149.16, '2026-08-28');
        $second = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-08-01', 'billing_period_end' => '2026-09-01']);
        $this->assertTrue($second['success'], $second['error'] ?? '');
        $expectedNew = round(20 * 10000 + 4.58 * 8500 + 45000, 2);
        $this->assertEqualsWithDelta($expectedNew, (float) $second['data']['subtotal'], 0.01);
    }

    public function testT15CurrentLessThanPreviousRejected()
    {
        $ownershipId = $this->createOwnership($this->waterGroupId);
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('meter_readings')->insert(['ownership_id' => $ownershipId, 'meter_number' => 'MTR-BAD', 'reading_date' => '2026-07-28', 'previous_reading' => 150.00, 'current_reading' => 140.00, 'usage' => -10.00, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-07-31', 'created_at' => $now, 'updated_at' => $now]);

        $result = $this->service->generateWater(['ownership_id' => $ownershipId, 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-08-01']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('lebih kecil', $result['error']);
    }
}