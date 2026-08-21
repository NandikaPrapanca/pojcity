<?php

namespace App\Controllers\Api;

use App\Services\InvoiceService;
use App\Services\AuthService;
use App\Services\WhatsAppService;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * InvoiceController
 *
 * Routes (all protected by 'auth' filter):
 *   GET  /api/v1/invoices                   → index()
 *   GET  /api/v1/invoices/{id}              → show($id)
 *   POST /api/v1/invoices/preview-tax       → previewTax()
 *   POST /api/v1/invoices/generate          → generate()
 *   GET  /api/v1/invoices/{id}/pdf          → downloadPdf($id)
 *   POST /api/v1/invoices/{id}/send-whatsapp → sendWhatsApp($id)
 */
class InvoiceController extends BaseApiController
{
    protected InvoiceService  $service;
    protected AuthService     $authService;
    protected WhatsAppService $whatsAppService;

    public function __construct()
    {
        $this->service          = new InvoiceService();
        $this->authService      = new AuthService();
        $this->whatsAppService  = new WhatsAppService();
    }

    // =========================================================================
    // Helper — extract authenticated user ID from JWT
    // =========================================================================

    /**
     * Decode the Bearer token from the current request and return the user id.
     * Returns null if the token is missing or invalid.
     */
    private function getAuthUserId(): ?int
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $user  = $this->authService->getUserFromToken($token);

        return $user ? (int)$user['id'] : null;
    }

    // =========================================================================
    // Endpoints
    // =========================================================================

    /**
     * GET /api/v1/invoices
     *
     * Query params (all optional):
     *   company_id, ownership_id, customer_id, status,
     *   issue_date_from, issue_date_to, search
     */
    public function index()
    {
        $filters = [
            'company_id'      => $this->request->getGet('company_id')      ?? '',
            'ownership_id'    => $this->request->getGet('ownership_id')    ?? '',
            'customer_id'     => $this->request->getGet('customer_id')     ?? '',
            'status'          => $this->request->getGet('status')          ?? '',
            'issue_date_from' => $this->request->getGet('issue_date_from') ?? '',
            'issue_date_to'   => $this->request->getGet('issue_date_to')   ?? '',
            'search'          => $this->request->getGet('search')          ?? '',
        ];

        // Remove empty strings so the service ignores unprovided filters
        $filters = array_filter($filters, fn($v) => $v !== '');

        return $this->success($this->service->getAll($filters));
    }

    /**
     * GET /api/v1/invoices/{id}
     */
    public function show(int $id)
    {
        $invoice = $this->service->getById($id);

        if (!$invoice) {
            return $this->notFound('Invoice tidak ditemukan.');
        }

        return $this->success($invoice);
    }

    /**
     * POST /api/v1/invoices/preview-tax
     *
     * Calculates and returns the tax breakdown for a set of billing items
     * WITHOUT persisting anything.
     *
     * Request body:
     * {
     *   "billing_item_ids": [1, 2, 3]
     * }
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "subtotal_taxable":    120000,
     *     "subtotal_nontaxable": 0,
     *     "subtotal_dpp":        120000,
     *     "dpp_nilai_lain":      110000,
     *     "ppn_amount":          13200,
     *     "grand_total":         133200,
     *     "tax_applied":         true
     *   }
     * }
     */
    public function previewTax()
    {
        $body = $this->getBody();

        $rules = [
            'billing_item_ids'   => 'required',
            'billing_item_ids.*' => 'integer',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $ids    = array_map('intval', (array)($body['billing_item_ids'] ?? []));
        $result = $this->service->previewTax($ids);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Perkiraan pajak berhasil dihitung.');
    }

    /**
     * POST /api/v1/invoices/generate
     *
     * Generates an invoice from one or more draft billing_items.
     * All items MUST belong to the same ownership.
     *
     * Request body:
     * {
     *   "billing_item_ids": [1, 2],
     *   "notes": "optional free text"
     * }
     *
     * Response 201: Full invoice with items and relations.
     * Response 401: Unauthenticated.
     * Response 422: Business rule violation.
     */
    public function generate()
    {
        // ── Auth check ────────────────────────────────────────────────────────
        $userId = $this->getAuthUserId();
        if (!$userId) {
            return $this->error('Tidak terautentikasi.', null, 401);
        }

        $body = $this->getBody();

        $rules = [
            'billing_item_ids'   => 'required',
            'billing_item_ids.*' => 'integer',
            'notes'              => 'permit_empty|max_length[1000]',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $ids   = array_map('intval', (array)($body['billing_item_ids'] ?? []));
        $notes = trim((string)($body['notes'] ?? ''));

        $result = $this->service->generateInvoice($ids, $userId, $notes);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Invoice berhasil digenerate.', 201);
    }

    /**
     * GET /api/v1/invoices/{id}/pdf
     *
     * Fetches the invoice with all relations and line items, renders the HTML
     * view template, converts it to PDF using Dompdf, and streams the file.
     *
     * The endpoint is protected by the 'auth' filter (same as all other routes).
     *
     * Response: application/pdf stream (inline view or forced download).
     */
    public function downloadPdf(int $id)
    {
        // ── 1. Load invoice data ──────────────────────────────────────────────
        $invoice = $this->service->getById($id);

        if (!$invoice) {
            return $this->notFound('Invoice tidak ditemukan.');
        }

        // ── 2. Load active Signature for invoice ──────────────────────────────
        $sigModel  = new \App\Models\SignatureModel();
        $companyId = $invoice['company_id'] ?? null;

        $signature = null;
        if ($companyId) {
            $signature = $sigModel->where('company_id', $companyId)
                ->where('is_active', 1)
                ->groupStart()
                    ->like('label', 'Pimpinan')
                    ->orLike('label', 'Direktur')
                ->groupEnd()
                ->first();

            if (!$signature) {
                $signature = $sigModel->where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->first();
            }
        }

        if (!$signature) {
            $signature = $sigModel->where('is_active', 1)
                ->groupStart()
                    ->like('label', 'Pimpinan')
                    ->orLike('label', 'Direktur')
                ->groupEnd()
                ->first();
        }

        if (!$signature) {
            $signature = $sigModel->where('is_active', 1)->first();
        }

        // Convert signature image to base64 for Dompdf rendering
        $signatureImageBase64 = null;
        if ($signature && !empty($signature['signature_path'])) {
            $paths = [
                WRITEPATH . $signature['signature_path'],
                WRITEPATH . 'uploads/' . ltrim($signature['signature_path'], '/'),
                FCPATH . $signature['signature_path'],
                ROOTPATH . 'writable/' . ltrim($signature['signature_path'], '/'),
            ];
            foreach ($paths as $p) {
                if (file_exists($p) && is_file($p)) {
                    $mime = mime_content_type($p) ?: 'image/png';
                    $signatureImageBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
                    break;
                }
            }
        }

        // ── 3. Check for Water Item & Meter Readings ──────────────────────────
        $hasWaterItem = false;
        $waterItem    = null;
        $items        = $invoice['items'] ?? [];

        foreach ($items as $item) {
            if (isset($item['billing_type']) && strtolower($item['billing_type']) === 'water') {
                $hasWaterItem = true;
                $waterItem    = $item;
                break;
            }
        }

        $meterReading      = null;
        $meterPhotoBase64  = null;

        if ($hasWaterItem && !empty($invoice['ownership_id'])) {
            $meterModel = new \App\Models\MeterReadingModel();
            $meterReading = $meterModel
                ->where('ownership_id', (int)$invoice['ownership_id'])
                ->where('deleted_at IS NULL')
                ->orderBy('reading_date', 'DESC')
                ->first();

            if ($meterReading && !empty($meterReading['photo_path'])) {
                $mPaths = [
                    WRITEPATH . $meterReading['photo_path'],
                    WRITEPATH . 'uploads/' . ltrim($meterReading['photo_path'], '/'),
                    FCPATH . $meterReading['photo_path'],
                    ROOTPATH . 'writable/' . ltrim($meterReading['photo_path'], '/'),
                ];
                foreach ($mPaths as $mp) {
                    if (file_exists($mp) && is_file($mp)) {
                        $mime = mime_content_type($mp) ?: 'image/jpeg';
                        $meterPhotoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($mp));
                        break;
                    }
                }
            }
        }

        // ── 4. Generate Terbilang (Spelled-out amount) ─────────────────────────
        $grandTotal     = (float)($invoice['grand_total'] ?? 0);
        $terbilangText  = ($grandTotal > 0) ? ($this->terbilang($grandTotal) . ' Rupiah') : 'Nol Rupiah';

        // ── 5. Render HTML template via CI4 view ──────────────────────────────
        $html = view('invoices/pdf_template', [
            'invoice'                => $invoice,
            'items'                  => $items,
            'signature'              => $signature,
            'signature_image_base64' => $signatureImageBase64,
            'has_water_item'         => $hasWaterItem,
            'water_item'             => $waterItem,
            'meter_reading'          => $meterReading,
            'meter_photo_base64'     => $meterPhotoBase64,
            'terbilang'              => $terbilangText,
        ]);

        // ── 3. Configure Dompdf ───────────────────────────────────────────────
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        // ── 4. Stream PDF to browser ──────────────────────────────────────────
        $filename = 'Invoice-' . preg_replace('/[\/\\\\]/', '-', $invoice['invoice_number']) . '.pdf';

        // Dompdf v3: stream() sends headers + body directly.
        // We set headers first to ensure Content-Disposition is correct.
        $output = $dompdf->output();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Content-Length', (string)strlen($output))
            ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->setBody($output);
    }

    /**
     * GET /api/v1/invoices/{id}/receipt
     *
     * Generates and streams the Kwitansi (Payment Receipt) PDF for a paid invoice.
     *
     * Response: application/pdf stream
     */
    public function downloadReceipt(int $id)
    {
        // ── 1. Load invoice data ──────────────────────────────────────────────
        $invoice = $this->service->getById($id);

        if (!$invoice) {
            return $this->notFound('Invoice tidak ditemukan.');
        }

        // ── 2. Load payment record ────────────────────────────────────────────
        $paymentModel = new \App\Models\PaymentModel();
        $payment      = $paymentModel->getForInvoice($id);

        $receiptNumber = 'KWT/' . preg_replace('/^INV\//', '', $invoice['invoice_number']);

        // ── 3. Load active Signature for receipt ──────────────────────────────
        $sigModel  = new \App\Models\SignatureModel();
        $companyId = $invoice['company_id'] ?? null;

        $signature = null;
        if ($companyId) {
            $signature = $sigModel->where('company_id', $companyId)
                ->where('is_active', 1)
                ->groupStart()
                    ->like('label', 'Pimpinan')
                    ->orLike('label', 'Direktur')
                ->groupEnd()
                ->first();

            if (!$signature) {
                $signature = $sigModel->where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->first();
            }
        }

        if (!$signature) {
            $signature = $sigModel->where('is_active', 1)
                ->groupStart()
                    ->like('label', 'Pimpinan')
                    ->orLike('label', 'Direktur')
                ->groupEnd()
                ->first();
        }

        if (!$signature) {
            $signature = $sigModel->where('is_active', 1)->first();
        }

        // Convert signature image to base64 for Dompdf
        $signatureImageBase64 = null;
        if ($signature && !empty($signature['signature_path'])) {
            $paths = [
                WRITEPATH . $signature['signature_path'],
                WRITEPATH . 'uploads/' . ltrim($signature['signature_path'], '/'),
                FCPATH . $signature['signature_path'],
                ROOTPATH . 'writable/' . ltrim($signature['signature_path'], '/'),
            ];
            foreach ($paths as $p) {
                if (file_exists($p) && is_file($p)) {
                    $mime = mime_content_type($p) ?: 'image/png';
                    $signatureImageBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
                    break;
                }
            }
        }

        // ── 4. Generate Terbilang (Spelled-out amount) ─────────────────────────
        $paidAmount    = $payment ? (float)$payment['amount'] : (float)($invoice['grand_total'] ?? 0);
        $terbilangText = ($paidAmount > 0) ? ($this->terbilang($paidAmount) . ' Rupiah') : 'Nol Rupiah';

        // ── 5. Build payment description ──────────────────────────────────────
        $items = $invoice['items'] ?? [];
        $descParts = [];
        foreach ($items as $item) {
            $descParts[] = $item['description'];
        }
        $paymentDescription = !empty($descParts) ? implode(', ', $descParts) : ('Tagihan Invoice ' . $invoice['invoice_number']);

        // ── 6. Render HTML template via CI4 view ──────────────────────────────
        $html = view('invoices/kwitansi_template', [
            'invoice'                => $invoice,
            'items'                  => $items,
            'payment'                => $payment,
            'receipt_number'         => $receiptNumber,
            'signature'              => $signature,
            'signature_image_base64' => $signatureImageBase64,
            'terbilang'              => $terbilangText,
            'paid_amount'            => $paidAmount,
            'payment_description'    => $paymentDescription,
        ]);

        // ── 7. Configure Dompdf ───────────────────────────────────────────────
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        // ── 8. Stream PDF to browser ──────────────────────────────────────────
        $filename = 'Kwitansi-' . preg_replace('/[\/\\\\]/', '-', $receiptNumber) . '.pdf';
        $output   = $dompdf->output();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Content-Length', (string)strlen($output))
            ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->setBody($output);
    }

    /**
     * POST /api/v1/invoices/{id}/send-whatsapp
     *
     * Formats an invoice summary message and sends it via WhatsApp
     * (mock for prototype — real Fonnte integration is a drop-in replacement).
     *
     * Response 200: { success, data: { log, phone, message } }
     * Response 404: Invoice not found.
     * Response 422: Service error.
     */
    public function sendWhatsApp(int $id)
    {
        // ── 1. Load invoice ───────────────────────────────────────────────────
        $invoice = $this->service->getById($id);

        if (!$invoice) {
            return $this->notFound('Invoice tidak ditemukan.');
        }

        // ── 2. Delegate to service ────────────────────────────────────────────
        $result = $this->whatsAppService->sendInvoiceNotification($invoice);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        $phone = $result['data']['phone'] ?? '—';

        return $this->success(
            $result['data'],
            "Pesan WhatsApp berhasil dikirim ke {$phone} (simulasi)."
        );
    }

    /**
     * Helper to convert numeric amount to spelled-out words in Bahasa Indonesia.
     */
    private function terbilang(float $angka): string
    {
        $angka = abs($angka);
        $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        $terbilang = '';

        if ($angka < 12) {
            $terbilang = ' ' . $baca[(int)$angka];
        } elseif ($angka < 20) {
            $terbilang = $this->terbilang($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $terbilang = $this->terbilang((int)($angka / 10)) . ' Puluh' . $this->terbilang((int)$angka % 10);
        } elseif ($angka < 200) {
            $terbilang = ' Seratus' . $this->terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $terbilang = $this->terbilang((int)($angka / 100)) . ' Ratus' . $this->terbilang((int)$angka % 100);
        } elseif ($angka < 2000) {
            $terbilang = ' Seribu' . $this->terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $terbilang = $this->terbilang((int)($angka / 1000)) . ' Ribu' . $this->terbilang(fmod($angka, 1000));
        } elseif ($angka < 1000000000) {
            $terbilang = $this->terbilang((int)($angka / 1000000)) . ' Juta' . $this->terbilang(fmod($angka, 1000000));
        } elseif ($angka < 1000000000000) {
            $terbilang = $this->terbilang((int)($angka / 1000000000)) . ' Miliar' . $this->terbilang(fmod($angka, 1000000000));
        } elseif ($angka < 1000000000000000) {
            $terbilang = $this->terbilang((int)($angka / 1000000000000)) . ' Triliun' . $this->terbilang(fmod($angka, 1000000000000));
        }

        return trim($terbilang);
    }
}
