<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class Phase2Seeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // ── 1. Company ────────────────────────────────────────────────────────
        $companyExists = $this->db->table('companies')
            ->where('name', 'PT Integrasi Prasarana Lingkungan')
            ->countAllResults();

        if (!$companyExists) {
            $this->db->table('companies')->insert([
                'name'       => 'PT Integrasi Prasarana Lingkungan',
                'address'    => 'Jl. Poj City No.1, Semarang, Jawa Tengah',
                'phone'      => '024-1234567',
                'email'      => 'admin@ipu-land.local',
                'npwp'       => '12.345.678.9-012.000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $companyId = $this->db->table('companies')
            ->where('name', 'PT Integrasi Prasarana Lingkungan')
            ->get()->getRowArray()['id'];

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
            $projectIds[$p['name']] = $this->db->table('projects')
                ->where('name', $p['name'])->get()->getRowArray()['id'];
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
            $clusterIds[$c] = $this->db->table('clusters')
                ->where('name', $c)->where('project_id', $projectIds['Ibiza'])
                ->get()->getRowArray()['id'];
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
            $blockIds[$b['name']] = $this->db->table('blocks')
                ->where('name', $b['name'])->where('cluster_id', $cid)
                ->get()->getRowArray()['id'];
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
        }

        // ── 6. IPL Rates ──────────────────────────────────────────────────────
        $iplRates = [
            ['project' => 'Ibiza',    'name' => 'Standar',      'rate_per_sqm' => 4500.00, 'effective_date' => '2024-01-01'],
            ['project' => 'Ibiza',    'name' => 'Khusus 50%',   'rate_per_sqm' => 2250.00, 'effective_date' => '2024-01-01'],
            ['project' => 'Mall 23',  'name' => 'Standar',      'rate_per_sqm' => 8000.00, 'effective_date' => '2024-01-01'],
        ];
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
        $groupId = $this->db->table('water_rate_groups')
            ->where('project_id', $projectIds['Ibiza'])->where('name', 'Grup Standar Ibiza')
            ->get()->getRowArray()['id'];

        $tiers = [
            ['min_usage' => 0,  'max_usage' => 20, 'rate_per_m3' => 7500.00],
            ['min_usage' => 20, 'max_usage' => 40, 'rate_per_m3' => 8500.00],
            ['min_usage' => 40, 'max_usage' => null,'rate_per_m3' => 9500.00],
        ];
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
            ['name' => 'Ariyan Hendrata',              'customer_type' => 'individual',  'whatsapp' => '081234567890'],
            ['name' => 'Yunita Wijaya',                 'customer_type' => 'individual',  'whatsapp' => '081234567891'],
            ['name' => 'Dewi Marliana',                 'customer_type' => 'individual',  'whatsapp' => '081234567892'],
            ['name' => 'PT Swarna Kanaka Parigrahaan', 'customer_type' => 'pt',           'whatsapp' => '081234567893'],
            ['name' => 'BINUS University',              'customer_type' => 'institution', 'whatsapp' => '081234567894'],
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
            $customerIds[$c['name']] = $this->db->table('customers')
                ->where('name', $c['name'])->get()->getRowArray()['id'];
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
            if (!$this->db->table('pics')
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

        echo "Phase2Seeder completed successfully.\n";
    }
}
