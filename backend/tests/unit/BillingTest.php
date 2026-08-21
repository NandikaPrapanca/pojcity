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

    // ─── Water Billing Test Helpers ───────────────────────────────────────────

    /**
     * Creates a full ownership fixture with a water rate group and tiers.
     *
     * Water rate group: abonemen = Rp 45,000
     * Tiers:
     *   Tier 1: 0–20 m³ @ Rp 7,500/m³
     *   Tier 2: 21–40 m³ @ Rp 8,500/m³
     *   Tier 3: 41+ m³ (open-ended) @ Rp 9,500/m³
     *
     * @return array ['ownership_id' => int, 'water_group_id' => int]
     */
    private function createSeedOwnershipWithWaterGroup(): array
    {
        $db = \Config\Database::connect();

        // Company
        $db->table('companies')->insert([
            'name'       => 'PT IPU Land',
            'phone'      => '024-9999999',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $companyId = $db->insertID();

        // Customer
        $db->table('customers')->insert([
            'company_id'      => $companyId,
            'name'            => 'Siti Rahayu',
            'customer_type'   => 'individual',
            'whatsapp'        => '081234567890',
            'billing_address' => 'Ibiza Blok C No. 7',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $customerId = $db->insertID();

        // Project
        $db->table('projects')->insert([
            'company_id'   => $companyId,
            'name'         => 'Ibiza Residential',
            'project_type' => 'residential',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $projectId = $db->insertID();

        // Cluster
        $db->table('clusters')->insert([
            'project_id' => $projectId,
            'name'       => 'Cluster Palma',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $clusterId = $db->insertID();

        // Block
        $db->table('blocks')->insert([
            'cluster_id' => $clusterId,
            'name'       => 'Blok C',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $blockId = $db->insertID();

        // Lot
        $db->table('lots')->insert([
            'block_id'   => $blockId,
            'lot_number' => '07',
            'area'       => 150.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lotId = $db->insertID();

        // IPL Rate (required FK on ownership)
        $db->table('ipl_rates')->insert([
            'project_id'     => $projectId,
            'name'           => 'Standard Ibiza',
            'rate_per_sqm'   => 4500.00,
            'effective_date' => '2026-01-01',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $iplRateId = $db->insertID();

        // Water Rate Group — abonemen Rp 45,000
        $db->table('water_rate_groups')->insert([
            'project_id' => $projectId,
            'name'       => 'Residential Standard',
            'abonemen'   => 45000.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $waterGroupId = $db->insertID();

        // Tier 1: 0–20 m³ @ Rp 7,500
        $db->table('water_rate_tiers')->insert([
            'water_rate_group_id' => $waterGroupId,
            'min_usage'           => 0.00,
            'max_usage'           => 20.00,
            'rate_per_m3'         => 7500.00,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Tier 2: 20–40 m³ @ Rp 8,500
        $db->table('water_rate_tiers')->insert([
            'water_rate_group_id' => $waterGroupId,
            'min_usage'           => 20.00,
            'max_usage'           => 40.00,
            'rate_per_m3'         => 8500.00,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Tier 3: 40+ m³ (open-ended) @ Rp 9,500
        $db->table('water_rate_tiers')->insert([
            'water_rate_group_id' => $waterGroupId,
            'min_usage'           => 40.00,
            'max_usage'           => null,
            'rate_per_m3'         => 9500.00,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Ownership — links customer + water group
        $db->table('ownerships')->insert([
            'customer_id'         => $customerId,
            'project_id'          => $projectId,
            'cluster_id'          => $clusterId,
            'block_id'            => $blockId,
            'lot_id'              => $lotId,
            'billing_address'     => 'Ibiza Blok C No. 07',
            'area'                => 150.00,
            'ipl_rate_id'         => $iplRateId,
            'water_rate_group_id' => $waterGroupId,
            'ownership_type'      => 'residential',
            'start_date'          => '2026-01-01',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
        $ownershipId = (int) $db->insertID();

        return ['ownership_id' => $ownershipId, 'water_group_id' => $waterGroupId];
    }

    /**
     * Seeds a meter reading for the given ownership.
     * Usage = current - previous (stored on the row).
     */
    private function seedMeterReading(int $ownershipId, float $previous, float $current, string $period = '2026-07-01'): int
    {
        $db = \Config\Database::connect();
        $db->table('meter_readings')->insert([
            'ownership_id'         => $ownershipId,
            'reading_date'         => $period,
            'previous_reading'     => $previous,
            'current_reading'      => $current,
            'usage'                => round($current - $previous, 2),
            'billing_period_start' => $period,
            'billing_period_end'   => date('Y-m-d', strtotime($period . ' +1 month')),
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    // ─── Water Billing Tests (Phase 5C) ──────────────────────────────────────

    /**
     * 11. Progressive tier calculation — the canonical 24.58 m³ boundary example
     *     from BUSINESS_RULES.md §7.
     *
     * Usage = 24.58 m³, Tiers: 0–20 @ 7,500 | 20–40 @ 8,500
     * Tier 1: 20 × 7,500 = 150,000
     * Tier 2: 4.58 × 8,500 = 38,930
     * Usage cost = 188,930
     * Abonemen = 45,000
     * Expected subtotal = 233,930
     */
    public function testGenerateWaterProgressiveTiers()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId = $seed['ownership_id'];

        // previous=296.02, current=320.60 → usage=24.58
        $this->seedMeterReading($ownershipId, 296.02, 320.60);

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($res['success'], 'generateWater should succeed: ' . ($res['error'] ?? ''));
        $this->assertNotNull($res['data']);
        $this->assertEquals('water', $res['data']['billing_type']);
        $this->assertEquals('draft', $res['data']['status']);

        // Authoritative subtotal: 150,000 + 38,930 + 45,000 = 233,930
        $this->assertEquals(233930.00, round((float) $res['data']['subtotal'], 2));

        // Usage snapshot preserved
        $this->assertEquals(24.58, round((float) $res['data']['quantity'], 2));
    }

    /**
     * 12. Tier snapshots in billing_item_tiers are correct.
     *     Expects 3 rows: Tier1, Tier2, plus abonemen row.
     */
    public function testGenerateWaterTierSnapshots()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId = $seed['ownership_id'];

        $this->seedMeterReading($ownershipId, 296.02, 320.60);

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($res['success']);
        $billingItemId = (int) $res['data']['id'];

        $db   = \Config\Database::connect();
        $rows = $db->table('billing_item_tiers')
                   ->where('billing_item_id', $billingItemId)
                   ->get()
                   ->getResultArray();

        // 2 progressive tier rows + 1 abonemen row = 3 total
        $this->assertCount(3, $rows);

        // Find and verify tier 1 row (20 m³ @ 7,500 = 150,000)
        $tier1 = null;
        $tier2 = null;
        $abRow = null;

        foreach ($rows as $row) {
            if ($row['tier_label'] === 'Abonemen') {
                $abRow = $row;
            } elseif (round((float) $row['usage_in_tier'], 2) === 20.00) {
                $tier1 = $row;
            } elseif (round((float) $row['usage_in_tier'], 2) === 4.58) {
                $tier2 = $row;
            }
        }

        $this->assertNotNull($tier1, 'Tier 1 (20 m³) row must exist');
        $this->assertEquals(7500.00, round((float) $tier1['rate'], 2));
        $this->assertEquals(150000.00, round((float) $tier1['amount'], 2));

        $this->assertNotNull($tier2, 'Tier 2 (4.58 m³) row must exist');
        $this->assertEquals(8500.00, round((float) $tier2['rate'], 2));
        $this->assertEquals(38930.00, round((float) $tier2['amount'], 2));

        $this->assertNotNull($abRow, 'Abonemen row must exist');
        $this->assertEquals(45000.00, round((float) $abRow['amount'], 2));
    }

    /**
     * 13. Duplicate water billing for the same ownership + period must be rejected.
     */
    public function testGenerateWaterDuplicateRejected()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId = $seed['ownership_id'];
        $this->seedMeterReading($ownershipId, 296.02, 320.60);

        $payload = [
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ];

        $first = $this->service->generateWater($payload);
        $this->assertTrue($first['success'], 'First generation must succeed');

        $duplicate = $this->service->generateWater($payload);
        $this->assertFalse($duplicate['success']);
        $this->assertStringContainsString('sudah ada', $duplicate['error']);
    }

    /**
     * 14. Ownership with no meter reading must be rejected.
     */
    public function testGenerateWaterNoMeterReading()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId = $seed['ownership_id'];
        // No meter reading seeded

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsStringIgnoringCase('meter', $res['error']);
    }

    /**
     * 15. Ownership without a water_rate_group_id must be rejected.
     */
    public function testGenerateWaterNoWaterRateGroup()
    {
        // Use the standard IPL-only ownership (no water group)
        $ownershipId = $this->createSeedOwnership();

        // Seed a meter reading so we don't fail on that check first
        $this->seedMeterReading($ownershipId, 100.00, 120.00);

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Kelompok Tarif Air', $res['error']);
    }

    /**
     * 16. Water rate group with no tier rows must be rejected.
     */
    public function testGenerateWaterNoTiers()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId   = $seed['ownership_id'];
        $waterGroupId  = $seed['water_group_id'];

        $this->seedMeterReading($ownershipId, 100.00, 120.00);

        // Remove all tiers from the group
        $db = \Config\Database::connect();
        $db->table('water_rate_tiers')->where('water_rate_group_id', $waterGroupId)->delete();

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsStringIgnoringCase('tier', $res['error']);
    }

    /**
     * 17. Zero usage (current_reading == previous_reading) must be rejected.
     */
    public function testGenerateWaterZeroUsage()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId = $seed['ownership_id'];

        // current == previous → usage = 0
        $this->seedMeterReading($ownershipId, 300.00, 300.00);

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('lebih besar dari 0', $res['error']);
    }

    /**
     * 18. Usage that exceeds the last tier boundary falls into the open-ended tier.
     *
     * Usage = 55 m³
     * Tier 1: 0–20 @7,500 → 20 × 7,500 = 150,000
     * Tier 2: 20–40 @8,500 → 20 × 8,500 = 170,000
     * Tier 3: 40+ @9,500  → 15 × 9,500 = 142,500
     * Usage cost = 462,500  +  Abonemen 45,000  = 507,500
     */
    public function testGenerateWaterOpenEndedTier()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId = $seed['ownership_id'];

        // previous=0, current=55 → usage=55
        $this->seedMeterReading($ownershipId, 0.00, 55.00);

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($res['success'], 'generateWater should succeed: ' . ($res['error'] ?? ''));

        // 150,000 + 170,000 + 142,500 + 45,000 = 507,500
        $this->assertEquals(507500.00, round((float) $res['data']['subtotal'], 2));

        // 3 tier rows + 1 abonemen row = 4 total
        $db   = \Config\Database::connect();
        $rows = $db->table('billing_item_tiers')
                   ->where('billing_item_id', (int) $res['data']['id'])
                   ->get()
                   ->getResultArray();
        $this->assertCount(4, $rows);
    }

    /**
     * 19. Immutability: values stored on billing_items and billing_item_tiers
     *     are snapshots and must not change after generation.
     */
    public function testGenerateWaterImmutability()
    {
        $seed = $this->createSeedOwnershipWithWaterGroup();
        $ownershipId  = $seed['ownership_id'];
        $waterGroupId = $seed['water_group_id'];

        $this->seedMeterReading($ownershipId, 296.02, 320.60); // usage=24.58

        $res = $this->service->generateWater([
            'ownership_id'         => $ownershipId,
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-08-01',
        ]);

        $this->assertTrue($res['success']);
        $billingItemId = (int) $res['data']['id'];
        $originalSubtotal = (float) $res['data']['subtotal'];

        // Now change the abonemen on the water rate group
        $db = \Config\Database::connect();
        $db->table('water_rate_groups')
           ->where('id', $waterGroupId)
           ->update(['abonemen' => 99999.00]);

        // Re-read the stored billing_item directly
        $row = $db->table('billing_items')->where('id', $billingItemId)->get()->getRowArray();
        $this->assertNotNull($row);

        // Stored subtotal must be the original snapshot value, not affected by the rate change
        $this->assertEquals($originalSubtotal, round((float) $row['subtotal'], 2));

        // Stored quantity (usage m³) must be 24.58
        $this->assertEquals(24.58, round((float) $row['quantity'], 2));

        // billing_item_tiers rows must still exist and be unchanged
        $tiers = $db->table('billing_item_tiers')
                    ->where('billing_item_id', $billingItemId)
                    ->get()
                    ->getResultArray();
        $this->assertCount(3, $tiers);

        // Abonemen tier row still stores original 45,000
        $abRow = array_filter($tiers, fn($t) => $t['tier_label'] === 'Abonemen');
        $abRow = array_values($abRow)[0];
        $this->assertEquals(45000.00, round((float) $abRow['amount'], 2));
    }
}

