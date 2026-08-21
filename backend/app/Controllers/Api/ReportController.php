<?php

namespace App\Controllers\Api;

use App\Models\InvoiceModel;
use App\Models\CompanyModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * ReportController
 *
 * Handles reporting and data export functionalities (Excel / XLSX).
 * Route: GET /api/v1/reports/export-invoices
 */
class ReportController extends BaseApiController
{
    protected InvoiceModel $invoiceModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->invoiceModel = new InvoiceModel();
        $this->companyModel = new CompanyModel();
    }

    /**
     * GET /api/v1/reports/export-invoices
     *
     * Generates a beautifully styled Excel workbook containing a comprehensive
     * recap of all invoices and their payment status.
     */
    public function exportInvoices()
    {
        // ── 1. Fetch data ─────────────────────────────────────────────────────
        $company = $this->companyModel->first();
        $companyName = $company['name'] ?? 'PT. INTEGRASI PRASARANA LINGKUNGAN';

        $invoices = $this->invoiceModel->getAllWithRelations();

        // ── 2. Initialize PhpSpreadsheet ──────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Invoice');

        // Show gridlines
        $sheet->setShowGridLines(true);

        // ── 3. Title & Header Banner ──────────────────────────────────────────
        $sheet->setCellValue('A1', strtoupper($companyName));
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('006400');

        $sheet->setCellValue('A2', 'LAPORAN REKAPITULASI TAGIHAN & FAKTUR INVOICE');
        $sheet->getStyle('A2')->getFont()->setSize(11)->setBold(true)->getColor()->setRGB('1F2937');

        $sheet->setCellValue('A3', 'Tanggal Export: ' . date('d F Y H:i') . ' | Total Data: ' . count($invoices) . ' Invoice');
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('6B7280');

        // ── 4. Table Header ───────────────────────────────────────────────────
        $headers = [
            'A5' => 'No',
            'B5' => 'No. Invoice',
            'C5' => 'Tgl. Terbit',
            'D5' => 'Jatuh Tempo',
            'E5' => 'Nama Customer',
            'F5' => 'Proyek / Unit',
            'G5' => 'Subtotal DPP (Rp)',
            'H5' => 'DPP Nilai Lain (Rp)',
            'I5' => 'PPN 12% (Rp)',
            'J5' => 'Grand Total (Rp)',
            'K5' => 'Status Tagihan',
            'L5' => 'Metode Bayar',
            'M5' => 'Tgl. Bayar',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $headerStyle = [
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '006400'], // Corporate Dark Green
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '004d00'],
                ],
            ],
        ];
        $sheet->getStyle('A5:M5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // ── 5. Data Rows ──────────────────────────────────────────────────────
        $rowNum = 6;
        $idx    = 1;

        $db = \Config\Database::connect();

        foreach ($invoices as $inv) {
            // Build unit string
            $unitParts = [];
            if (!empty($inv['project_name'])) $unitParts[] = $inv['project_name'];
            if (!empty($inv['cluster_name']))  $unitParts[] = $inv['cluster_name'];
            if (!empty($inv['block_name']))    $unitParts[] = $inv['block_name'];
            if (!empty($inv['lot_number']))    $unitParts[] = 'No. ' . $inv['lot_number'];
            $unitText = !empty($unitParts) ? implode(' · ', $unitParts) : ($inv['ownership_type'] ?? '-');

            // Status label
            $statusLabel = match (strtolower($inv['status'] ?? 'draft')) {
                'paid'      => 'Lunas',
                'sent'      => 'Terkirim',
                'draft'     => 'Draft',
                'overdue'   => 'Jatuh Tempo',
                'cancelled' => 'Dibatalkan',
                default     => ucfirst($inv['status'] ?? '-'),
            };

            // Payment info if paid
            $paymentMethod = '-';
            $paymentDate   = '-';
            if (($inv['status'] ?? '') === 'paid') {
                $payRow = $db->table('payments')->where('invoice_id', $inv['id'])->orderBy('id', 'DESC')->get()->getRowArray();
                if ($payRow) {
                    $paymentMethod = $payRow['payment_method'] ?? 'Transfer Bank';
                    $paymentDate   = !empty($payRow['payment_date']) ? date('d/m/Y', strtotime($payRow['payment_date'])) : '-';
                }
            }

            $sheet->setCellValue('A' . $rowNum, $idx);
            $sheet->setCellValue('B' . $rowNum, $inv['invoice_number']);
            $sheet->setCellValue('C' . $rowNum, !empty($inv['issue_date']) ? date('d/m/Y', strtotime($inv['issue_date'])) : '-');
            $sheet->setCellValue('D' . $rowNum, !empty($inv['due_date']) ? date('d/m/Y', strtotime($inv['due_date'])) : '-');
            $sheet->setCellValue('E' . $rowNum, $inv['customer_name'] ?? '-');
            $sheet->setCellValue('F' . $rowNum, $unitText);
            $sheet->setCellValue('G' . $rowNum, (float)($inv['subtotal_dpp'] ?? 0));
            $sheet->setCellValue('H' . $rowNum, (float)($inv['dpp_nilai_lain'] ?? 0));
            $sheet->setCellValue('I' . $rowNum, (float)($inv['ppn_amount'] ?? 0));
            $sheet->setCellValue('J' . $rowNum, (float)($inv['grand_total'] ?? 0));
            $sheet->setCellValue('K' . $rowNum, $statusLabel);
            $sheet->setCellValue('L' . $rowNum, $paymentMethod);
            $sheet->setCellValue('M' . $rowNum, $paymentDate);

            // Striped row background
            if ($idx % 2 === 0) {
                $sheet->getStyle("A{$rowNum}:M{$rowNum}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F9FAFB');
            }

            // Alignments
            $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$rowNum}:D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$rowNum}:M{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Number formatting for currencies
            $sheet->getStyle("G{$rowNum}:J{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $rowNum++;
            $idx++;
        }

        $lastDataRow = $rowNum - 1;

        // ── 6. Summary Totals Row ─────────────────────────────────────────────
        if ($lastDataRow >= 6) {
            $sheet->setCellValue("A{$rowNum}", 'TOTAL KESELURUHAN');
            $sheet->mergeCells("A{$rowNum}:F{$rowNum}");

            $sheet->setCellValue("G{$rowNum}", "=SUM(G6:G{$lastDataRow})");
            $sheet->setCellValue("H{$rowNum}", "=SUM(H6:H{$lastDataRow})");
            $sheet->setCellValue("I{$rowNum}", "=SUM(I6:I{$lastDataRow})");
            $sheet->setCellValue("J{$rowNum}", "=SUM(J6:J{$lastDataRow})");

            $sheet->getStyle("G{$rowNum}:J{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $totalStyle = [
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => '006400'],
                    'size'  => 10,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'ECFDF5'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '006400']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '006400']],
                ],
            ];
            $sheet->getStyle("A{$rowNum}:M{$rowNum}")->applyFromArray($totalStyle);
            $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        // Apply grid borders to all table cells
        $tableRange = "A5:M" . ($lastDataRow >= 6 ? $rowNum : 5);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');

        // ── 7. Auto-fit column widths ─────────────────────────────────────────
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── 8. Generate & Stream Output ───────────────────────────────────────
        $writer   = new Xlsx($spreadsheet);
        $filename = 'Rekap_Invoice_' . date('Ymd_His') . '.xlsx';

        // Write to php://temp buffer
        $tempFile = fopen('php://temp', 'r+');
        $writer->save($tempFile);
        rewind($tempFile);
        $content = stream_get_contents($tempFile);
        fclose($tempFile);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Content-Length', (string)strlen($content))
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
    }
}
