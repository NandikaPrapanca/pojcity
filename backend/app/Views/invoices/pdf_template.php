<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Invoice <?= esc($invoice['invoice_number']) ?></title>
<style>
  /* ── Reset ─────────────────────────────────────────── */
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 9.5pt;
    color: #1a1a1a;
    line-height: 1.4;
    background: #ffffff;
  }

  /* ── Page wrapper ───────────────────────────────────── */
  .page {
    padding: 32px 38px;
    min-height: 100%;
  }

  /* ── Header ─────────────────────────────────────────── */
  .header-table {
    width: 100%;
    border-bottom: 2.5px solid #006400;
    padding-bottom: 12px;
    margin-bottom: 16px;
  }
  .header-table td { vertical-align: top; }
  .company-name {
    font-size: 13.5pt;
    font-weight: bold;
    color: #006400;
    line-height: 1.2;
  }
  .company-meta {
    font-size: 8pt;
    color: #555;
    margin-top: 3px;
    line-height: 1.45;
  }
  .invoice-badge {
    text-align: right;
  }
  .invoice-badge-label {
    font-size: 18pt;
    font-weight: bold;
    color: #006400;
    letter-spacing: -0.5px;
    text-transform: uppercase;
  }
  .invoice-number {
    font-size: 9.5pt;
    color: #444;
    margin-top: 2px;
  }

  /* ── Info band (dates + billed-to) ──────────────────── */
  .info-table {
    width: 100%;
    margin-bottom: 18px;
  }
  .info-table td { vertical-align: top; }
  .info-box {
    background: #f5faf6;
    border: 1px solid #c8e6ce;
    border-radius: 4px;
    padding: 9px 12px;
  }
  .info-box-label {
    font-size: 7.5pt;
    font-weight: bold;
    color: #006400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .info-row {
    width: 100%;
    margin-bottom: 2px;
  }
  .info-row td.lbl {
    font-size: 8pt;
    color: #666;
    width: 85px;
    vertical-align: top;
  }
  .info-row td.val {
    font-size: 8pt;
    color: #1a1a1a;
    font-weight: bold;
    vertical-align: top;
  }
  .billed-name {
    font-size: 9.5pt;
    font-weight: bold;
    color: #1a1a1a;
    margin-bottom: 2px;
  }
  .billed-meta {
    font-size: 8pt;
    color: #555;
    line-height: 1.45;
  }

  /* ── Status badge ───────────────────────────────────── */
  .status-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 3px;
    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4px;
  }
  .status-draft    { background: #dbeafe; color: #1e40af; }
  .status-sent     { background: #ede9fe; color: #5b21b6; }
  .status-paid     { background: #dcfce7; color: #15803d; }
  .status-overdue  { background: #fee2e2; color: #991b1b; }
  .status-cancelled{ background: #f3f4f6; color: #374151; }

  /* ── Items table ────────────────────────────────────── */
  .section-title {
    font-size: 8pt;
    font-weight: bold;
    color: #006400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
  }
  .items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
    font-size: 8.5pt;
  }
  .items-table thead tr {
    background: #006400;
    color: #ffffff;
  }
  .items-table thead th {
    padding: 7px 9px;
    text-align: left;
    font-size: 8pt;
    font-weight: bold;
    color: #ffffff;
    border: none;
  }
  .items-table thead th.right { text-align: right; }
  .items-table thead th.center { text-align: center; }
  .items-table tbody tr { border-bottom: 1px solid #e5e7eb; }
  .items-table tbody tr:nth-child(even) { background: #f9fafb; }
  .items-table tbody td {
    padding: 7px 9px;
    vertical-align: top;
    font-size: 8.5pt;
  }
  .items-table tbody td.right { text-align: right; }
  .items-table tbody td.center { text-align: center; }
  .item-desc { font-weight: bold; color: #111827; }
  .item-period { font-size: 7.5pt; color: #6b7280; margin-top: 1px; }
  .tax-tag {
    font-size: 6.5pt;
    background: #dcfce7;
    color: #15803d;
    border-radius: 2px;
    padding: 1px 4px;
    font-weight: bold;
  }
  .no-tax-tag {
    font-size: 6.5pt;
    background: #f3f4f6;
    color: #6b7280;
    border-radius: 2px;
    padding: 1px 4px;
    font-weight: bold;
  }

  /* ── Totals ─────────────────────────────────────────── */
  .totals-wrapper {
    width: 100%;
    margin-bottom: 14px;
  }
  .totals-table {
    width: 270px;
    border-collapse: collapse;
    float: right;
    font-size: 8.5pt;
  }
  .totals-table td {
    padding: 4px 8px;
    border-bottom: 1px solid #f3f4f6;
  }
  .totals-table td.lbl { color: #555; }
  .totals-table td.amt { text-align: right; font-weight: bold; color: #111827; }
  .totals-table td.formula {
    font-size: 7pt;
    color: #9ca3af;
    padding-top: 0;
    padding-bottom: 3px;
  }
  .totals-table tr.separator td { border-top: 1px solid #d1d5db; padding-top: 5px; }
  .totals-table tr.grand-total td {
    background: #006400;
    color: #ffffff;
    font-weight: bold;
    font-size: 10pt;
    border: none;
  }
  .totals-table tr.grand-total td.amt { text-align: right; color: #ffffff; }
  .totals-table tr.ppn td.lbl { color: #d97706; }
  .totals-table tr.ppn td.amt { color: #d97706; }

  /* ── Terbilang Row ──────────────────────────────────── */
  .terbilang-box {
    background: #f9fafb;
    border-left: 3px solid #006400;
    padding: 6px 10px;
    margin-bottom: 16px;
    font-size: 8pt;
    font-style: italic;
    color: #374151;
  }
  .terbilang-label {
    font-weight: bold;
    font-style: normal;
    color: #006400;
    margin-right: 4px;
  }

  /* ── Notes ──────────────────────────────────────────── */
  .notes-box {
    border: 1px dashed #c8e6ce;
    border-radius: 4px;
    padding: 7px 10px;
    margin-bottom: 16px;
    font-size: 8pt;
    color: #555;
    background: #f9fafb;
  }
  .notes-label {
    font-size: 7.5pt;
    font-weight: bold;
    color: #006400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
  }

  /* ── Bottom Section (Meter Photos + Signature) ──────── */
  .bottom-table {
    width: 100%;
    margin-top: 8px;
  }
  .bottom-table td {
    vertical-align: top;
  }

  /* ── Water Meter Photos ─────────────────────────────── */
  .meter-section {
    width: 290px;
  }
  .meter-title {
    font-size: 7.5pt;
    font-weight: bold;
    color: #006400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
  }
  .meter-cards-table {
    width: 100%;
    border-collapse: collapse;
  }
  .meter-cards-table td {
    width: 50%;
    padding: 0 4px;
    vertical-align: top;
  }
  .meter-card {
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #fafafa;
    padding: 4px;
    text-align: center;
  }
  .meter-card-header {
    font-size: 7pt;
    font-weight: bold;
    color: #374151;
    margin-bottom: 3px;
    text-transform: uppercase;
  }
  .meter-photo-frame {
    width: 100%;
    height: 65px;
    background: #e5e7eb;
    border: 1px dashed #9ca3af;
    border-radius: 3px;
    display: block;
    text-align: center;
    overflow: hidden;
  }
  .meter-photo-img {
    max-height: 63px;
    max-width: 100%;
    display: inline-block;
  }
  .meter-placeholder-text {
    font-size: 6.5pt;
    color: #6b7280;
    line-height: 65px;
  }
  .meter-reading-val {
    font-size: 7.5pt;
    font-weight: bold;
    color: #006400;
    margin-top: 3px;
  }

  /* ── Signature Box ──────────────────────────────────── */
  .sig-container {
    width: 200px;
    float: right;
    text-align: center;
  }
  .sig-place-date {
    text-align: center;
    margin-bottom: 4px;
    font-size: 8pt;
    color: #374151;
  }
  .sig-image-container {
    height: 52px;
    margin: 3px 0;
    text-align: center;
  }
  .sig-image {
    max-height: 48px;
    max-width: 140px;
    display: inline-block;
  }
  .sig-space {
    height: 44px;
  }
  .sig-line {
    border-top: 1px solid #374151;
    padding-top: 4px;
    font-weight: bold;
    color: #1a1a1a;
    font-size: 8.5pt;
  }
  .sig-title {
    font-size: 7.5pt;
    color: #6b7280;
    margin-top: 1px;
  }

  /* ── Footer ─────────────────────────────────────────── */
  .footer {
    border-top: 1px solid #e5e7eb;
    padding-top: 6px;
    margin-top: 18px;
    text-align: center;
    font-size: 7pt;
    color: #9ca3af;
  }

  /* ── Utility ─────────────────────────────────────────── */
  .clearfix::after { content: ''; display: table; clear: both; }
</style>
</head>
<body>
<div class="page">

  <!-- ── HEADER ──────────────────────────────────────────────────────── -->
  <table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
      <td style="width:60%;">
        <div class="company-name"><?= esc($invoice['company_name'] ?? 'PT. INTEGRASI PRASARANA LINGKUNGAN') ?></div>
        <div class="company-meta">
          <?php if (!empty($invoice['company_address'])): ?>
            <?= esc($invoice['company_address']) ?><br>
          <?php endif; ?>
          <?php if (!empty($invoice['company_phone'])): ?>
            Telp: <?= esc($invoice['company_phone']) ?>
          <?php endif; ?>
          <?php if (!empty($invoice['company_npwp'])): ?>
            &nbsp;|&nbsp; NPWP: <?= esc($invoice['company_npwp']) ?>
          <?php endif; ?>
        </div>
      </td>
      <td class="invoice-badge">
        <div class="invoice-badge-label">Invoice</div>
        <div class="invoice-number"><?= esc($invoice['invoice_number']) ?></div>
        <?php
          $statusMap = [
            'draft'    => ['label' => 'Draft',      'class' => 'status-draft'],
            'sent'     => ['label' => 'Terkirim',   'class' => 'status-sent'],
            'paid'     => ['label' => 'Lunas',      'class' => 'status-paid'],
            'overdue'  => ['label' => 'Jatuh Tempo','class' => 'status-overdue'],
            'cancelled'=> ['label' => 'Dibatalkan', 'class' => 'status-cancelled'],
          ];
          $st = $statusMap[$invoice['status']] ?? ['label' => $invoice['status'], 'class' => 'status-draft'];
        ?>
        <div style="margin-top:4px;">
          <span class="status-badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
        </div>
      </td>
    </tr>
  </table>

  <!-- ── INFO BAND (Dates + Billed To) ───────────────────────────────── -->
  <table class="info-table" cellpadding="0" cellspacing="0">
    <tr>
      <!-- Dates column -->
      <td style="width:40%; padding-right:10px;">
        <div class="info-box">
          <div class="info-box-label">Detail Invoice</div>
          <table class="info-row" cellpadding="0" cellspacing="0">
            <tr><td class="lbl">Tanggal Terbit</td><td class="val">:&nbsp;<?= esc(date('d F Y', strtotime($invoice['issue_date']))) ?></td></tr>
            <tr><td class="lbl">Jatuh Tempo</td><td class="val">:&nbsp;<?= esc(date('d F Y', strtotime($invoice['due_date']))) ?></td></tr>
          </table>
        </div>
      </td>
      <!-- Billed To column -->
      <td style="width:60%;">
        <div class="info-box">
          <div class="info-box-label">Tagihan Kepada</div>
          <div class="billed-name"><?= esc($invoice['customer_name'] ?? '—') ?></div>
          <div class="billed-meta">
            <?php
              $unitParts = [];
              if (!empty($invoice['project_name'])) $unitParts[] = $invoice['project_name'];
              if (!empty($invoice['cluster_name']))  $unitParts[] = $invoice['cluster_name'];
              if (!empty($invoice['block_name']))    $unitParts[] = $invoice['block_name'];
              if (!empty($invoice['lot_number']))    $unitParts[] = 'No. ' . $invoice['lot_number'];
              if ($unitParts) echo esc(implode(', ', $unitParts)) . '<br>';
            ?>
            <?php if (!empty($invoice['billing_address'])): ?>
              <?= esc($invoice['billing_address']) ?><br>
            <?php endif; ?>
            <?php if (!empty($invoice['customer_npwp'])): ?>
              NPWP: <?= esc($invoice['customer_npwp']) ?>
            <?php endif; ?>
          </div>
        </div>
      </td>
    </tr>
  </table>

  <!-- ── ITEMS TABLE ─────────────────────────────────────────────────── -->
  <div class="section-title">Rincian Tagihan</div>
  <table class="items-table" cellpadding="0" cellspacing="0">
    <thead>
      <tr>
        <th style="width:38%;">Deskripsi</th>
        <th class="center" style="width:18%;">Kuantitas</th>
        <th class="right" style="width:22%;">Biaya</th>
        <th class="right" style="width:22%;">Total Biaya</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td>
          <div class="item-desc"><?= esc($item['description']) ?></div>
          <div class="item-period">
            Periode: <?= esc($item['billing_period_start']) ?> s/d <?= esc($item['billing_period_end']) ?>
            &nbsp;
            <?php if ((int)$item['apply_tax'] === 1): ?>
              <span class="tax-tag">PPN</span>
            <?php else: ?>
              <span class="no-tax-tag">Non-PPN</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($item['notes'])): ?>
            <div class="item-period"><?= esc($item['notes']) ?></div>
          <?php endif; ?>
        </td>
        <td class="center">
          <?= number_format((float)$item['quantity'], 2, ',', '.') ?> <?= esc($item['unit']) ?>
        </td>
        <td class="right"><?= 'Rp ' . number_format((float)$item['unit_price'], 0, ',', '.') ?></td>
        <td class="right" style="font-weight:bold;"><?= 'Rp ' . number_format((float)$item['subtotal'], 0, ',', '.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- ── TOTALS ──────────────────────────────────────────────────────── -->
  <div class="clearfix totals-wrapper">
    <table class="totals-table" cellpadding="0" cellspacing="0">
      <tr>
        <td class="lbl">Subtotal DPP</td>
        <td class="amt">Rp <?= number_format((float)$invoice['subtotal_dpp'], 0, ',', '.') ?></td>
      </tr>
      <tr>
        <td class="lbl" style="font-size:7.5pt;">DPP Nilai Lain</td>
        <td class="amt">Rp <?= number_format((float)$invoice['dpp_nilai_lain'], 0, ',', '.') ?></td>
      </tr>
      <tr>
        <td colspan="2" class="formula">&nbsp;&nbsp;= 11/12 &times; Subtotal DPP Kena Pajak</td>
      </tr>
      <tr class="separator ppn">
        <td class="lbl">PPN <?= number_format((float)$invoice['ppn_rate'], 0) ?>%</td>
        <td class="amt">Rp <?= number_format((float)$invoice['ppn_amount'], 0, ',', '.') ?></td>
      </tr>
      <tr>
        <td colspan="2" class="formula">&nbsp;&nbsp;= 12% &times; DPP Nilai Lain</td>
      </tr>
      <tr class="separator grand-total">
        <td class="lbl" style="padding-left:8px;">Total Tagihan</td>
        <td class="amt" style="padding-right:8px;">Rp <?= number_format((float)$invoice['grand_total'], 0, ',', '.') ?></td>
      </tr>
    </table>
  </div>

  <!-- ── TERBILANG (Spelled-out words) ───────────────────────────────── -->
  <div class="terbilang-box">
    <span class="terbilang-label">Terbilang:</span>
    <?= esc($terbilang) ?>
  </div>

  <!-- ── NOTES ──────────────────────────────────────────────────────── -->
  <?php if (!empty($invoice['notes'])): ?>
  <div class="notes-box">
    <div class="notes-label">Catatan</div>
    <?= esc($invoice['notes']) ?>
  </div>
  <?php endif; ?>

  <!-- ── BOTTOM SECTION: Meter Photos (Left) & Signature (Right) ───── -->
  <table class="bottom-table" cellpadding="0" cellspacing="0">
    <tr>
      <!-- Left: Water Meter Photos (Conditional) -->
      <td style="width:60%;">
        <?php if (!empty($has_water_item)): ?>
          <div class="meter-section">
            <div class="meter-title">📷 Dokumentasi Meteran Air</div>
            <table class="meter-cards-table" cellpadding="0" cellspacing="0">
              <tr>
                <!-- Meter Awal -->
                <td>
                  <div class="meter-card">
                    <div class="meter-card-header">Meter Awal (Lalu)</div>
                    <div class="meter-photo-frame">
                      <span class="meter-placeholder-text">Foto Meter Lalu</span>
                    </div>
                    <div class="meter-reading-val">
                      <?= !empty($meter_reading['previous_reading']) ? number_format((float)$meter_reading['previous_reading'], 2, ',', '.') . ' m³' : '0,00 m³' ?>
                    </div>
                  </div>
                </td>
                <!-- Meter Akhir -->
                <td>
                  <div class="meter-card">
                    <div class="meter-card-header">Meter Akhir (Kini)</div>
                    <div class="meter-photo-frame">
                      <?php if (!empty($meter_photo_base64)): ?>
                        <img src="<?= $meter_photo_base64 ?>" class="meter-photo-img" alt="Foto Meter Kini" />
                      <?php else: ?>
                        <span class="meter-placeholder-text">Foto Meter Kini</span>
                      <?php endif; ?>
                    </div>
                    <div class="meter-reading-val">
                      <?= !empty($meter_reading['current_reading']) ? number_format((float)$meter_reading['current_reading'], 2, ',', '.') . ' m³' : (!empty($water_item['quantity']) ? number_format((float)$water_item['quantity'], 2, ',', '.') . ' m³' : '0,00 m³') ?>
                    </div>
                  </div>
                </td>
              </tr>
            </table>
          </div>
        <?php endif; ?>
      </td>

      <!-- Right: Dynamic Signature -->
      <td style="width:40%;">
        <div class="sig-container">
          <div class="sig-place-date">
            <?= esc(date('d F Y', strtotime($invoice['issue_date']))) ?>
          </div>
          <div class="sig-place-date">Hormat Kami,</div>

          <?php if (!empty($signature_image_base64)): ?>
            <div class="sig-image-container">
              <img src="<?= $signature_image_base64 ?>" class="sig-image" alt="Tanda Tangan" />
            </div>
          <?php else: ?>
            <div class="sig-space"></div>
          <?php endif; ?>

          <div class="sig-line">
            <?= esc($signature['name'] ?? $invoice['company_name'] ?? 'Setia Dharma Loka') ?>
          </div>
          <div class="sig-title">
            <?= esc($signature['position'] ?? $signature['label'] ?? 'Pimpinan') ?>
          </div>
        </div>
      </td>
    </tr>
  </table>

  <!-- ── FOOTER ─────────────────────────────────────────────────────── -->
  <div class="footer">
    Dokumen ini diterbitkan secara elektronik dan sah tanpa tanda tangan basah.
    &nbsp;|&nbsp;
    Diterbitkan: <?php
      $createdAt = $invoice['created_at'] ?? null;
      $ts = $createdAt ? strtotime((string)$createdAt) : false;
      echo esc($ts ? date('d/m/Y H:i', $ts) : date('d/m/Y H:i'));
    ?>
  </div>

</div>
</body>
</html>
