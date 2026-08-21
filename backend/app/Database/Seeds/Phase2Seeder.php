<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class Phase2Seeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // ── 1. Company ────────────────────────────────────────────────────────
        $companyRow = $this->db->table('companies')
            ->where('name', 'PT Integrasi Prasarana Lingkungan')
            ->get()->getRowArray();

        if (!$companyRow) {
            $this->db->table('companies')->insert([
                'name'       => 'PT Integrasi Prasarana Lingkungan',
                'address'    => 'Jl. Poj City No.1, Semarang, Jawa Tengah',
                'phone'      => '024-1234567',
                'email'      => 'admin@ipu-land.local',
                'npwp'       => '12.345.678.9-012.000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $companyId = (int)$this->db->insertID();
        } else {
            $companyId = (int)$companyRow['id'];
        }

        // ── 2. Projects ───────────────────────────────────────────────────────
        $projects = [
            ['name' => 'Ibiza',           'project_type' => 'residential', 'address' => 'Kawasan Poj City, Semarang'],
            ['name' => 'Mall 23',          'project_type' => 'commercial',  'address' => 'Jl. Mall 23, Semarang'],
            ['name' => 'BINUS University', 'project_type' => 'commercial',  'address' => 'Jl. BINUS No.1, Semarang'],
            ['name' => 'BINUS School',     'project_type' => 'commercial',  'address' => 'Jl. BINUS School No.2, Semarang'],
        ];
        foreach ($projects as $p) {
            if (!$this->db->table('projects')->where('name', $p['name'])->countAllResults()) {
                $this->db->table('projects')->insert(array_merge($p, [
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
        $projectIds = [];
        foreach ($projects as $p) {
            $pRow = $this->db->table('projects')->where('name', $p['name'])->get()->getRowArray();
            $projectIds[$p['name']] = $pRow ? (int)$pRow['id'] : null;
        }

        // ── 3. Clusters (Ibiza only) ──────────────────────────────────────────
        $clusters = ['Cluster A', 'Cluster B'];
        $clusterIds = [];
        foreach ($clusters as $c) {
            if (!$this->db->table('clusters')->where('name', $c)->where('project_id', $projectIds['Ibiza'])->countAllResults()) {
                $this->db->table('clusters')->insert([
                    'project_id' => $projectIds['Ibiza'],
                    'name'       => $c,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $cRow = $this->db->table('clusters')
                ->where('name', $c)->where('project_id', $projectIds['Ibiza'])
                ->get()->getRowArray();
            $clusterIds[$c] = $cRow ? (int)$cRow['id'] : null;
        }

        // ── 4. Blocks ─────────────────────────────────────────────────────────
        $blocksData = [
            ['cluster' => 'Cluster A', 'name' => 'Blok A1'],
            ['cluster' => 'Cluster A', 'name' => 'Blok A2'],
            ['cluster' => 'Cluster B', 'name' => 'Blok B1'],
        ];
        $blockIds = [];
        foreach ($blocksData as $b) {
            $cid = $clusterIds[$b['cluster']];
            if (!$this->db->table('blocks')->where('name', $b['name'])->where('cluster_id', $cid)->countAllResults()) {
                $this->db->table('blocks')->insert([
                    'cluster_id' => $cid,
                    'name'       => $b['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $bRow = $this->db->table('blocks')
                ->where('name', $b['name'])->where('cluster_id', $cid)
                ->get()->getRowArray();
            $blockIds[$b['name']] = $bRow ? (int)$bRow['id'] : null;
        }

        // ── 5. Lots ───────────────────────────────────────────────────────────
        $lotsData = [
            ['block' => 'Blok A1', 'lot_number' => 'A1-01', 'area' => 120.00],
            ['block' => 'Blok A1', 'lot_number' => 'A1-02', 'area' => 120.00],
            ['block' => 'Blok A1', 'lot_number' => 'A1-03', 'area' => 180.00],
            ['block' => 'Blok A2', 'lot_number' => 'A2-01', 'area' => 150.00],
            ['block' => 'Blok A2', 'lot_number' => 'A2-02', 'area' => 150.00],
            ['block' => 'Blok B1', 'lot_number' => 'B1-01', 'area' => 200.00],
            ['block' => 'Blok B1', 'lot_number' => 'B1-02', 'area' => 240.00],
        ];
        $lotIds = [];
        foreach ($lotsData as $l) {
            $bid = $blockIds[$l['block']];
            if (!$this->db->table('lots')->where('lot_number', $l['lot_number'])->where('block_id', $bid)->countAllResults()) {
                $this->db->table('lots')->insert([
                    'block_id'   => $bid,
                    'lot_number' => $l['lot_number'],
                    'area'       => $l['area'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $lRow = $this->db->table('lots')->where('lot_number', $l['lot_number'])->where('block_id', $bid)->get()->getRowArray();
            $lotIds[$l['lot_number']] = $lRow ? (int)$lRow['id'] : null;
        }

        // ── 6. IPL Rates ──────────────────────────────────────────────────────
        $iplRates = [
            ['project' => 'Ibiza',    'name' => 'Standar',      'rate_per_sqm' => 4500.00, 'effective_date' => '2024-01-01'],
            ['project' => 'Ibiza',    'name' => 'Khusus 50%',   'rate_per_sqm' => 2250.00, 'effective_date' => '2024-01-01'],
            ['project' => 'Mall 23',  'name' => 'Standar',      'rate_per_sqm' => 8000.00, 'effective_date' => '2024-01-01'],
        ];
        $iplRateIds = [];
        foreach ($iplRates as $r) {
            $pid = $projectIds[$r['project']];
            if (!$this->db->table('ipl_rates')
                ->where('project_id', $pid)->where('name', $r['name'])->countAllResults()) {
                $this->db->table('ipl_rates')->insert([
                    'project_id'     => $pid,
                    'name'           => $r['name'],
                    'rate_per_sqm'   => $r['rate_per_sqm'],
                    'effective_date' => $r['effective_date'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
            $rRow = $this->db->table('ipl_rates')->where('project_id', $pid)->where('name', $r['name'])->get()->getRowArray();
            $iplRateIds[$r['project'] . '_' . $r['name']] = $rRow ? (int)$rRow['id'] : null;
        }

        // ── 7. Water Rate Group + Tiers (Ibiza) ───────────────────────────────
        if (!$this->db->table('water_rate_groups')
            ->where('project_id', $projectIds['Ibiza'])->where('name', 'Grup Standar Ibiza')->countAllResults()) {
            $this->db->table('water_rate_groups')->insert([
                'project_id' => $projectIds['Ibiza'],
                'name'       => 'Grup Standar Ibiza',
                'abonemen'   => 45000.00,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $wgRow = $this->db->table('water_rate_groups')
            ->where('project_id', $projectIds['Ibiza'])->where('name', 'Grup Standar Ibiza')
            ->get()->getRowArray();
        $groupId = $wgRow ? (int)$wgRow['id'] : null;

        $tiers = [
            ['min_usage' => 0,  'max_usage' => 20, 'rate_per_m3' => 7500.00],
            ['min_usage' => 20, 'max_usage' => 40, 'rate_per_m3' => 8500.00],
            ['min_usage' => 40, 'max_usage' => null,'rate_per_m3' => 9500.00],
        ];
        if ($groupId) {
            foreach ($tiers as $t) {
                if (!$this->db->table('water_rate_tiers')
                    ->where('water_rate_group_id', $groupId)
                    ->where('min_usage', $t['min_usage'])->countAllResults()) {
                    $this->db->table('water_rate_tiers')->insert(array_merge($t, [
                        'water_rate_group_id' => $groupId,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]));
                }
            }
        }

        // ── 8. Tax Configuration ──────────────────────────────────────────────
        if (!$this->db->table('tax_configurations')->where('label', 'PPN 12% (2024)')->countAllResults()) {
            $this->db->table('tax_configurations')->insert([
                'label'                      => 'PPN 12% (2024)',
                'dpp_multiplier_numerator'   => 11,
                'dpp_multiplier_denominator' => 12,
                'ppn_rate'                   => 0.1200,
                'effective_date'             => '2024-01-01',
                'is_active'                  => 1,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ]);
        }

        // ── 9. Signatures ─────────────────────────────────────────────────────
        $signatures = [
            ['label' => 'Admin',    'name' => 'Nur Cahyadi',          'position' => 'Administrator'],
            ['label' => 'Direktur', 'name' => 'Ir. Budi Santosa M.T.','position' => 'Direktur Utama'],
        ];
        foreach ($signatures as $s) {
            if (!$this->db->table('signatures')
                ->where('company_id', $companyId)->where('label', $s['label'])->countAllResults()) {
                $this->db->table('signatures')->insert(array_merge($s, [
                    'company_id'  => $companyId,
                    'is_active'   => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]));
            }
        }

        // ── 10. Customers ─────────────────────────────────────────────────────
        $customers = [
            ['name' => 'Ariyan Hendrata',              'customer_type' => 'individual',  'whatsapp' => '081234567890', 'email' => 'ariyan@example.com'],
            ['name' => 'Yunita Wijaya',                 'customer_type' => 'individual',  'whatsapp' => '081234567891', 'email' => 'yunita@example.com'],
            ['name' => 'Dewi Marliana',                 'customer_type' => 'individual',  'whatsapp' => '081234567892', 'email' => 'dewi@example.com'],
            ['name' => 'PT Swarna Kanaka Parigrahaan', 'customer_type' => 'pt',           'whatsapp' => '081234567893', 'email' => 'finance@swarna.co.id'],
            ['name' => 'BINUS University',              'customer_type' => 'institution', 'whatsapp' => '081234567894', 'email' => 'facility@binus.edu'],
        ];
        $customerIds = [];
        foreach ($customers as $c) {
            if (!$this->db->table('customers')->where('name', $c['name'])->countAllResults()) {
                $this->db->table('customers')->insert(array_merge($c, [
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
            $cRow = $this->db->table('customers')->where('name', $c['name'])->get()->getRowArray();
            $customerIds[$c['name']] = $cRow ? (int)$cRow['id'] : null;
        }

        // ── 11. PICs ──────────────────────────────────────────────────────────
        $picsData = [
            [
                'customer' => 'Ariyan Hendrata',
                'name'     => 'Ariyan Hendrata',
                'phone'    => '081234567890',
                'position' => 'Pemilik',
                'is_primary' => 1,
            ],
            [
                'customer' => 'BINUS University',
                'name'     => 'Budi Santoso',
                'phone'    => '081298765432',
                'position' => 'Manager',
                'is_primary' => 1,
            ],
        ];
        foreach ($picsData as $p) {
            $cid = $customerIds[$p['customer']];
            if ($cid && !$this->db->table('pics')
                ->where('customer_id', $cid)->where('name', $p['name'])->countAllResults()) {
                $this->db->table('pics')->insert([
                    'customer_id' => $cid,
                    'name'        => $p['name'],
                    'phone'       => $p['phone'],
                    'position'    => $p['position'],
                    'is_primary'  => $p['is_primary'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        // ── 12. Ownerships ────────────────────────────────────────────────────
        $ownershipsData = [
            [
                'customer_id'         => $customerIds['Ariyan Hendrata'] ?? 1,
                'project_id'          => $projectIds['Ibiza'] ?? 1,
                'cluster_id'          => $clusterIds['Cluster A'] ?? 1,
                'block_id'            => $blockIds['Blok A1'] ?? 1,
                'lot_id'              => $lotIds['A1-01'] ?? 1,
                'billing_address'     => 'Ibiza Cluster A Blok A1 No. A1-01, Semarang',
                'area'                => 120.00,
                'ipl_rate_id'         => $iplRateIds['Ibiza_Standar'] ?? 1,
                'water_rate_group_id' => $groupId,
                'ownership_type'      => 'residential',
                'start_date'          => '2024-01-01',
                'notes'               => 'Demo data unit Ariyan',
            ],
            [
                'customer_id'         => $customerIds['Yunita Wijaya'] ?? 2,
                'project_id'          => $projectIds['Ibiza'] ?? 1,
                'cluster_id'          => $clusterIds['Cluster A'] ?? 1,
                'block_id'            => $blockIds['Blok A1'] ?? 1,
                'lot_id'              => $lotIds['A1-02'] ?? 2,
                'billing_address'     => 'Ibiza Cluster A Blok A1 No. A1-02, Semarang',
                'area'                => 120.00,
                'ipl_rate_id'         => $iplRateIds['Ibiza_Standar'] ?? 1,
                'water_rate_group_id' => $groupId,
                'ownership_type'      => 'residential',
                'start_date'          => '2024-01-01',
                'notes'               => 'Demo data unit Yunita',
            ],
            [
                'customer_id'         => $customerIds['BINUS University'] ?? 5,
                'project_id'          => $projectIds['BINUS University'] ?? 3,
                'cluster_id'          => null,
                'block_id'            => null,
                'lot_id'              => null,
                'billing_address'     => 'Kawasan Kampus BINUS Semarang',
                'area'                => 5000.00,
                'ipl_rate_id'         => $iplRateIds['Ibiza_Standar'] ?? 1,
                'water_rate_group_id' => null,
                'ownership_type'      => 'commercial',
                'start_date'          => '2024-01-01',
                'notes'               => 'Gedung Kampus BINUS Semarang',
            ],
        ];

        foreach ($ownershipsData as $own) {
            $exists = $this->db->table('ownerships')
                ->where('customer_id', $own['customer_id'])
                ->where('project_id', $own['project_id'])
                ->where('deleted_at IS NULL')
                ->countAllResults();

            if (!$exists) {
                $this->db->table('ownerships')->insert(array_merge($own, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        echo "Phase2Seeder completed successfully.\n";
    }
}
