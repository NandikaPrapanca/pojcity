<?php

namespace App\Services;

use Config\Database;

/**
 * WhatsAppService — Mock Implementation
 *
 * This is a PROTOTYPE / DEMO implementation.
 * It does NOT send real WhatsApp messages via Fonnte or any other provider.
 *
 * What it does:
 *  1. Formats a polite, professional invoice summary message in Bahasa Indonesia.
 *  2. Simulates a ~2-second network delay (usleep).
 *  3. Inserts an audit row into the whatsapp_logs table with status = 'simulated'.
 *  4. Returns a success result with the log record.
 *
 * ── Migration to Real API ────────────────────────────────────────────────────
 * When integrating with Fonnte (or any other provider), replace the
 * simulateSend() block in send() with a real HTTP call:
 *
 *   $client = \Config\Services::curlrequest();
 *   $response = $client->post('https://api.fonnte.com/send', [
 *       'headers' => ['Authorization' => env('FONNTE_TOKEN')],
 *       'form_params' => [
 *           'target'  => $phone,
 *           'message' => $message,
 *       ],
 *   ]);
 *   // Parse $response->getBody() for status.
 */
class WhatsAppService
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Send (or simulate sending) an invoice notification via WhatsApp.
     *
     * @param  array  $invoice  Full invoice array from InvoiceModel::findWithRelations()
     * @return array  ['success' => bool, 'data' => log_row|null, 'error' => string|null]
     */
    public function sendInvoiceNotification(array $invoice): array
    {
        // ── 1. Resolve destination phone ──────────────────────────────────────
        $phone = $this->resolvePhone($invoice);

        // ── 2. Format message ─────────────────────────────────────────────────
        $message = $this->formatInvoiceMessage($invoice);

        // ── 3. Simulate delivery (replace this block for real integration) ────
        $status       = 'simulated';
        $errorMessage = null;

        try {
            $this->simulateSend($phone, $message);
        } catch (\Throwable $e) {
            $status       = 'failed';
            $errorMessage = $e->getMessage();
        }

        // ── 4. Write audit log ────────────────────────────────────────────────
        $now = date('Y-m-d H:i:s');
        $this->db->table('whatsapp_logs')->insert([
            'invoice_id'    => (int)$invoice['id'],
            'customer_phone'=> $phone,
            'message_body'  => $message,
            'status'        => $status,
            'error_message' => $errorMessage,
            'created_at'    => $now,
        ]);

        $logId  = $this->db->insertID();
        $logRow = $this->db->table('whatsapp_logs')->where('id', $logId)->get()->getRowArray();

        if ($status === 'failed') {
            return [
                'success' => false,
                'error'   => 'Gagal mengirim pesan WhatsApp: ' . $errorMessage,
                'data'    => $logRow,
            ];
        }

        return [
            'success' => true,
            'error'   => null,
            'data'    => [
                'log'     => $logRow,
                'phone'   => $phone,
                'message' => $message,
            ],
        ];
    }

    /**
     * Get delivery history for a specific invoice.
     *
     * @param  int $invoiceId
     * @return array<int, array>
     */
    public function getLogsForInvoice(int $invoiceId): array
    {
        return $this->db->table('whatsapp_logs')
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Resolve the best available phone number from the invoice record.
     * Priority: customer_whatsapp → customer phone → placeholder.
     */
    private function resolvePhone(array $invoice): ?string
    {
        $phone = $invoice['customer_whatsapp'] ?? null;

        if (empty($phone)) {
            // Fallback: look up directly from customers table
            $customer = $this->db->table('customers')
                ->where('id', (int)$invoice['customer_id'])
                ->get()
                ->getRowArray();
            $phone = $customer['whatsapp'] ?? $customer['phone'] ?? null;
        }

        // Normalise Indonesian phone: strip leading 0, prefix with 62
        if ($phone) {
            $phone = preg_replace('/[^0-9]/', '', (string)$phone);
            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            } elseif (!str_starts_with($phone, '62')) {
                $phone = '62' . $phone;
            }
        }

        return $phone ?: null;
    }

    /**
     * Format a polite, professional invoice summary message in Bahasa Indonesia.
     *
     * Keeps it under ~300 characters to fit WhatsApp preview and remain readable.
     */
    private function formatInvoiceMessage(array $invoice): string
    {
        $customerName  = $invoice['customer_name']  ?? 'Pelanggan';
        $invoiceNumber = $invoice['invoice_number'] ?? '—';
        $grandTotal    = 'Rp ' . number_format((float)($invoice['grand_total'] ?? 0), 0, ',', '.');
        $dueDate       = '—';

        if (!empty($invoice['due_date'])) {
            $ts = strtotime((string)$invoice['due_date']);
            if ($ts) {
                $dueDate = date('d/m/Y', $ts);
            }
        }

        // Build unit label
        $unitParts = [];
        if (!empty($invoice['project_name'])) $unitParts[] = $invoice['project_name'];
        if (!empty($invoice['cluster_name']))  $unitParts[] = $invoice['cluster_name'];
        if (!empty($invoice['block_name']))    $unitParts[] = $invoice['block_name'];
        if (!empty($invoice['lot_number']))    $unitParts[] = 'No. ' . $invoice['lot_number'];
        $unit = $unitParts ? implode(', ', $unitParts) : '';

        $lines = [
            "Yth. Bapak/Ibu {$customerName},",
            "",
            "Berikut informasi tagihan dari kami:",
            "📄 No. Invoice : {$invoiceNumber}",
            "🏠 Unit        : {$unit}",
            "💰 Total       : {$grandTotal}",
            "📅 Jatuh Tempo : {$dueDate}",
            "",
            "Mohon melakukan pembayaran sebelum jatuh tempo.",
            "Terima kasih atas kepercayaan Anda.",
            "",
            "Hormat Kami,",
            $invoice['company_name'] ?? 'PT. INTEGRASI PRASARANA LINGKUNGAN',
        ];

        return implode("\n", $lines);
    }

    /**
     * Simulate a WhatsApp send with a realistic ~2-second delay.
     *
     * In a real implementation, replace this with an HTTP call to Fonnte or
     * another WhatsApp Business API provider.
     *
     * @throws \RuntimeException  (never, in this mock — but real APIs may throw)
     */
    private function simulateSend(?string $phone, string $message): void
    {
        // Simulate ~2-second network round-trip
        usleep(2_000_000); // 2 seconds in microseconds

        // Prototype note: In a real scenario, log to CI4 logger for debugging.
        log_message('info', "[WhatsAppService][MOCK] Would send to {$phone}: " . substr($message, 0, 80) . '...');
    }
}
