<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Kwitansi <?= esc($receipt_number) ?></title>
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
    margin-bottom: 18px;
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
  .receipt-badge {
    text-align: right;
  }
  .receipt-badge-label {
    font-size: 20pt;
    font-weight: bold;
    color: #006400;
    letter-spacing: -0.5px;
    text-transform: uppercase;
  }
  .receipt-number {
    font-size: 10pt;
    color: #444;
    margin-top: 2px;
    font-weight: bold;
  }
  .paid-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
    border-radius: 3px;
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
  }

  /* ── Main Form Table (Kwitansi Lines) ───────────────── */
  .form-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 18px;
  }
  .form-table td {
    padding: 7px 0;
    vertical-align: top;
    font-size: 9pt;
  }
  .form-table td.label-col {
    width: 155px;
    color: #4b5563;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 8pt;
    letter-spacing: 0.3px;
  }
  .form-table td.colon-col {
    width: 15px;
    color: #4b5563;
    font-weight: bold;
    text-align: center;
  }
  .form-table td.value-col {
    color: #111827;
    border-bottom: 1px dashed #d1d5db;
    padding-bottom: 5px;
  }
  .form-value-bold {
    font-weight: bold;
    color: #111827;
    font-size: 9.5pt;
  }
  .terbilang-text {
    font-style: italic;
    background: #f3f4f6;
    padding: 4px 8px;
    border-radius: 3px;
    color: #1f2937;
    line-height: 1.4;
  }

  /* ── Amount and Breakdown Table ─────────────────────── */
  .breakdown-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
  }
  .breakdown-table td {
    vertical-align: top;
  }

  /* Big Amount Box */
  .amount-box {
    background: #006400;
    color: #ffffff;
    padding: 14px 18px;
    border-radius: 6px;
    width: 250px;
    text-align: center;
  }
  .amount-box-label {
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #d1fae5;
    margin-bottom: 4px;
  }
  .amount-box-value {
    font-size: 15pt;
    font-weight: bold;
    color: #ffffff;
    letter-spacing: -0.5px;
  }

  /* Tax Summary Sub-table */
  .tax-summary-table {
    width: 260px;
    border-collapse: collapse;
    float: right;
    font-size: 8.5pt;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
  }
  .tax-summary-table td {
    padding: 4px 8px;
    border-bottom: 1px solid #f3f4f6;
  }
  .tax-summary-table td.lbl { color: #555; }
  .tax-summary-table td.amt { text-align: right; font-weight: bold; color: #111827; }
  .tax-summary-table tr.header-row td {
    background: #f9fafb;
    font-weight: bold;
    color: #006400;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.4px;
  }

  /* ── Items breakdown list ───────────────────────────── */
  .items-mini-table {
    width: 100%;
    border-collapse: collapse;
    margin: 6px 0;
    font-size: 8pt;
  }
  .items-mini-table th {
    background: #f3f4f6;
    padding: 3px 6px;
    text-align: left;
    color: #4b5563;
    font-size: 7.5pt;
    border-bottom: 1px solid #e5e7eb;
  }
  .items-mini-table td {
    padding: 3px 6px;
    border-bottom: 1px solid #f9fafb;
  }
  .items-mini-table td.right { text-align: right; }

  /* ── Bottom Section (Payment Info + Signature) ──────── */
  .bottom-table {
    width: 100%;
    margin-top: 10px;
  }
  .bottom-table td {
    vertical-align: top;
  }

  /* Payment info box */
  .payment-info-box {
    width: 280px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 9px 12px;
  }
  .payment-info-title {
    font-size: 7.5pt;
    font-weight: bold;
    color: #006400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .payment-info-row {
    font-size: 8pt;
    color: #4b5563;
    margin-bottom: 2px;
  }
  .payment-info-row strong {
    color: #111827;
  }

  /* Signature container */
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
    margin-top: 20px;
    text-align: center;
    font-size: 7pt;
    color: #9ca3af;
  }

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
      <td class="receipt-badge">
        <div class="receipt-badge-label">Kwitansi</div>
        <div class="receipt-number"><?= esc($receipt_number) ?></div>
        <div>
          <span class="paid-badge">LUNAS</span>
        </div>
      </td>
    </tr>
  </table>

  <!-- ── FORM TABLE (Kwitansi Lines) ─────────────────────────────────── -->
  <table class="form-table" cellpadding="0" cellspacing="0">
    <tr>
      <td class="label-col">Sudah Terima Dari</td>
      <td class="colon-col">:</td>
      <td class="value-col">
        <span class="form-value-bold"><?= esc($invoice['customer_name'] ?? '—') ?></span>
      </td>
    </tr>
    <tr>
      <td class="label-col">Alamat / Unit</td>
      <td class="colon-col">:</td>
      <td class="value-col">
        <?php
          $unitParts = [];
          if (!empty($invoice['project_name'])) $unitParts[] = $invoice['project_name'];
          if (!empty($invoice['cluster_name']))  $unitParts[] = $invoice['cluster_name'];
          if (!empty($invoice['block_name']))    $unitParts[] = $invoice['block_name'];
          if (!empty($invoice['lot_number']))    $unitParts[] = 'No. ' . $invoice['lot_number'];
          if ($unitParts) echo esc(implode(', ', $unitParts)) . ' — ';
          echo esc($invoice['billing_address'] ?? '');
        ?>
      </td>
    </tr>
    <tr>
      <td class="label-col">Banyaknya Uang</td>
      <td class="colon-col">:</td>
      <td class="value-col">
        <div class="terbilang-text"><?= esc($terbilang) ?></div>
      </td>
    </tr>
    <tr>
      <td class="label-col">Untuk Pembayaran</td>
      <td class="colon-col">:</td>
      <td class="value-col">
        <div>Pembayaran tagihan atas <strong>Invoice <?= esc($invoice['invoice_number']) ?></strong>:</div>
        <table class="items-mini-table" cellpadding="0" cellspacing="0">
          <thead>
            <tr>
              <th>Rincian Tagihan</th>
              <th style="width:25%; text-align:center;">Periode</th>
              <th style="width:25%; text-align:right;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td><?= esc($it['description']) ?></td>
              <td style="text-align:center; font-size:7pt; color:#666;"><?= esc($it['billing_period_start']) ?> s/d <?= esc($it['billing_period_end']) ?></td>
              <td class="right">Rp <?= number_format((float)$it['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </td>
    </tr>
  </table>

  <!-- ── AMOUNT BOX & TAX BREAKDOWN ──────────────────────────────────── -->
  <table class="breakdown-table" cellpadding="0" cellspacing="0">
    <tr>
      <!-- Big Amount Box -->
      <td style="width:50%;">
        <div class="amount-box">
          <div class="amount-box-label">Jumlah Pembayaran</div>
          <div class="amount-box-value">Rp <?= number_format($paid_amount, 0, ',', '.') ?></div>
        </div>
      </td>
      <!-- Tax Breakdown -->
      <td style="width:50%;">
        <table class="tax-summary-table" cellpadding="0" cellspacing="0">
          <tr class="header-row">
            <td colspan="2">Kalkulasi Pajak (PPN 12%)</td>
          </tr>
          <tr>
            <td class="lbl">Subtotal DPP</td>
            <td class="amt">Rp <?= number_format((float)$invoice['subtotal_dpp'], 0, ',', '.') ?></td>
          </tr>
          <tr>
            <td class="lbl" style="font-size:7.5pt;">DPP Nilai Lain (11/12)</td>
            <td class="amt">Rp <?= number_format((float)$invoice['dpp_nilai_lain'], 0, ',', '.') ?></td>
          </tr>
          <tr>
            <td class="lbl">PPN <?= number_format((float)$invoice['ppn_rate'], 0) ?>%</td>
            <td class="amt" style="color:#d97706;">Rp <?= number_format((float)$invoice['ppn_amount'], 0, ',', '.') ?></td>
          </tr>
          <tr style="background:#f0fdf4;">
            <td class="lbl" style="font-weight:bold; color:#006400;">Total Lunas</td>
            <td class="amt" style="color:#006400; font-size:9pt;">Rp <?= number_format((float)$invoice['grand_total'], 0, ',', '.') ?></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- ── BOTTOM SECTION: Payment Info (Left) & Signature (Right) ─────── -->
  <table class="bottom-table" cellpadding="0" cellspacing="0">
    <tr>
      <!-- Left: Payment details -->
      <td style="width:55%;">
        <div class="payment-info-box">
          <div class="payment-info-title">Informasi Pembayaran</div>
          <div class="payment-info-row">
            Metode: <strong><?= esc($payment['payment_method'] ?? 'Transfer Bank') ?></strong>
          </div>
          <div class="payment-info-row">
            Tanggal Bayar: <strong><?= esc(date('d F Y', strtotime($payment['payment_date'] ?? $invoice['issue_date']))) ?></strong>
          </div>
          <?php if (!empty($payment['reference_number'])): ?>
            <div class="payment-info-row">
              No. Referensi: <strong><?= esc($payment['reference_number']) ?></strong>
            </div>
          <?php endif; ?>
          <div class="payment-info-row" style="margin-top:4px; font-size:7.5pt; color:#6b7280;">
            Pembayaran telah diverifikasi dan invoice dinyatakan lunas.
          </div>
        </div>
      </td>

      <!-- Right: Dynamic Signature -->
      <td style="width:45%;">
        <div class="sig-container">
          <div class="sig-place-date">
            Semarang, <?= esc(date('d F Y', strtotime($payment['payment_date'] ?? $invoice['issue_date']))) ?>
          </div>
          <div class="sig-place-date">Penerima / Kasir,</div>

          <?php if (!empty($signature_image_base64)): ?>
            <div class="sig-image-container">
              <img src="<?= $signature_image_base64 ?>" class="sig-image" alt="Tanda Tangan" />
            </div>
          <?php else: ?>
            <div class="sig-space"></div>
          <?php endif; ?>

          <div class="sig-line">
            <?= esc($signature['name'] ?? 'Setia Dharma Loka') ?>
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
    Kwitansi ini merupakan bukti pembayaran yang sah dan dicetak secara otomatis melalui Sistem IPU Billing.
    &nbsp;|&nbsp;
    Dicetak: <?= esc(date('d/m/Y H:i')) ?>
  </div>

</div>
</body>
</html>
