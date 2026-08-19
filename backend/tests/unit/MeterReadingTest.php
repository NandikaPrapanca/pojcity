<?php

namespace App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\MeterReadingService;
use App\Models\MeterReadingModel;

class MeterReadingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected MeterReadingService $service;
    protected MeterReadingModel   $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MeterReadingService();
        $this->model   = new MeterReadingModel();
    }

    public function testUsageCalculationAndValidation()
    {
        $db = \Config\Database::connect();

        // 1. Validation error: missing ownership
        $res = $this->service->create([
            'ownership_id'         => '',
            'reading_date'         => '2026-08-01',
            'previous_reading'     => '100.00',
            'current_reading'      => '115.50',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-07-31',
        ]);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Kepemilikan wajib dipilih', $res['error']);

        // Insert required dummy company
        $db->table('companies')->insert([
            'name'       => 'PT IPU Land',
            'phone'      => '024-1234567',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $companyId = $db->insertID();

        // Insert customer
        $db->table('customers')->insert([
            'company_id'      => $companyId,
            'name'            => 'Budi Santoso',
            'customer_type'   => 'individual',
            'whatsapp'        => '08123456789',
            'billing_address' => 'Jl. Merdeka 10',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $customerId = $db->insertID();

        // Insert project
        $db->table('projects')->insert([
            'company_id'   => $companyId,
            'name'         => 'Poj City Residential',
            'project_type' => 'residential',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $projectId = $db->insertID();

        // Insert cluster
        $db->table('clusters')->insert([
            'project_id' => $projectId,
            'name'       => 'Cluster Emerald',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $clusterId = $db->insertID();

        // Insert block
        $db->table('blocks')->insert([
            'cluster_id' => $clusterId,
            'name'       => 'A1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $blockId = $db->insertID();

        // Insert lot
        $db->table('lots')->insert([
            'block_id'   => $blockId,
            'lot_number' => '12',
            'area'       => 120.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lotId = $db->insertID();

        // Insert IPL rate
        $db->table('ipl_rates')->insert([
            'project_id'     => $projectId,
            'name'           => 'Standard Residensial',
            'rate_per_sqm'   => 4500.00,
            'effective_date' => '2026-01-01',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $iplRateId = $db->insertID();

        // Insert ownership
        $db->table('ownerships')->insert([
            'customer_id'    => $customerId,
            'project_id'     => $projectId,
            'cluster_id'     => $clusterId,
            'block_id'       => $blockId,
            'lot_id'         => $lotId,
            'area'           => 120.00,
            'ipl_rate_id'    => $iplRateId,
            'ownership_type' => 'residential',
            'start_date'     => '2026-01-01',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $ownershipId = $db->insertID();

        // 2. Validation error: current reading < previous reading
        $res = $this->service->create([
            'ownership_id'         => $ownershipId,
            'reading_date'         => '2026-08-01',
            'previous_reading'     => '100.00',
            'current_reading'      => '95.00',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-07-31',
        ]);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('tidak boleh lebih kecil', $res['error']);

        // 3. Validation error: billing period end < start
        $res = $this->service->create([
            'ownership_id'         => $ownershipId,
            'reading_date'         => '2026-08-01',
            'previous_reading'     => '100.00',
            'current_reading'      => '115.00',
            'billing_period_start' => '2026-08-01',
            'billing_period_end'   => '2026-07-01',
        ]);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('lebih awal', $res['error']);

        // 4. Success test with exact usage calculation (301.30 - 296.02 = 5.28)
        $res = $this->service->create([
            'ownership_id'         => $ownershipId,
            'meter_number'         => 'MTR-TEST-01',
            'reading_date'         => '2026-08-01',
            'previous_reading'     => '296.02',
            'current_reading'      => '301.30',
            'billing_period_start' => '2026-07-01',
            'billing_period_end'   => '2026-07-31',
            'notes'                => 'Uji unit test meter reading',
        ]);

        $this->assertTrue($res['success']);
        $this->assertNotNull($res['data']);
        $this->assertEquals(5.28, (float) $res['data']['usage']);

        $createdId = (int) $res['data']['id'];

        // 5. Test getById and findWithRelations
        $fetched = $this->service->getById($createdId);
        $this->assertNotNull($fetched);
        $this->assertEquals('MTR-TEST-01', $fetched['meter_number']);
        $this->assertEquals(5.28, (float) $fetched['usage']);
        $this->assertEquals('Budi Santoso', $fetched['customer_name']);

        // 6. Test latest reading lookup
        $latest = $this->service->getLatest($ownershipId);
        $this->assertNotNull($latest);
        $this->assertEquals($createdId, (int) $latest['id']);
        $this->assertEquals(301.30, (float) $latest['current_reading']);

        // 7. Test update
        $updated = $this->service->update($createdId, [
            'current_reading' => '305.00',
        ]);
        $this->assertTrue($updated['success']);
        $this->assertEquals(8.98, (float) $updated['data']['usage']); // 305.00 - 296.02 = 8.98

        // 8. Test soft delete
        $deleted = $this->service->delete($createdId);
        $this->assertTrue($deleted);

        $afterDelete = $this->model->find($createdId);
        $this->assertNull($afterDelete); // Soft deleted item is excluded by default
    }
}
