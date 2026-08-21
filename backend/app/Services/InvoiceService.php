<?php

namespace App\Services;

use App\Models\CompanyModel;
use App\Models\BillingItemModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceItemModel;
use Config\Database;

/**
 * InvoiceService
 *
 * Core service for generating invoices from draft billing_items.
 *
 * ─── Tax Math (Indonesian DPP Nilai Lain — 12% PPN scheme) ───────────────────
 *
 *   Only items with apply_tax = 1 contribute to the taxable base.
 *   Only items with apply_tax = 0 are non-taxable.
 *
 *   subtotal_taxable     = SUM(subtotal) of items where apply_tax = 1
 *   subtotal_nontaxable  = SUM(subtotal) of items where apply_tax = 0
 *   subtotal_dpp         = subtotal_taxable + subtotal_nontaxable  (stored on invoice)
 *
 *   DPP Nilai Lain       = (11 / 12) × subtotal_taxable
 *   PPN                  = 12%       × DPP Nilai Lain
 *   grand_total          = subtotal_dpp + PPN
 *
 * ─── Invoice Number Format ───────────────────────────────────────────────────
 *   INV/YYYY/MM/NNNN    e.g. INV/2026/08/0001
 *   Sequence counter is stored in system_settings.key = 'invoice_sequence_YYYY_MM'
 *   SELECT … FOR UPDATE locks the row to prevent race conditions.
 *
 * ─── Architecture Rules ─────────────────────────────────────────────────────
 *   1. All billing_items in the request must share the same ownership_id.
 *   2. All items must have status = 'draft'.
 *   3. Company is auto-selected (first active company).
 *   4. created_by is read from the caller (JWT user id passed in).
 *   5. invoice_items rows are immutable snapshots — never update/delete them.
 *   6. billing_items.status is set to 'invoiced' after successful generation.
 */
class InvoiceService
{
    protected CompanyModel     $companyModel;
    protected BillingItemModel $billingItemModel;
    protected InvoiceModel     $invoiceModel;
    protected InvoiceItemModel $invoiceItemModel;

    public function __construct()
    {
        $this->companyModel     = new CompanyModel();
        $this->billingItemModel = new BillingItemModel();
        $this->invoiceModel     = new InvoiceModel();
        $this->invoiceItemModel = new InvoiceItemModel();
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Get all invoices with optional filters.
     *
     * @param  array $filters  See InvoiceModel::getAllWithRelations()
     * @return array
     */
    public function getAll(array $filters = []): array
    {
        return $this->invoiceModel->getAllWithRelations($filters);
    }

    /**
     * Get a single invoice with its line items and relations.
     *
     * @param  int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        return $this->invoiceModel->findWithRelations($id);
    }

    /**
     * Preview tax calculation for a set of billing item IDs — does NOT persist anything.
     *
     * @param  array<int> $billingItemIds
     * @return array  ['success' => bool, 'data' => [...], 'error' => string]
     */
    public function previewTax(array $billingItemIds): array
    {
        $items = $this->loadAndValidateItems($billingItemIds, $ownershipId, $errors);

        if (!empty($errors)) {
            return ['success' => false, 'error' => $errors[0], 'data' => null];
        }

        $calc = $this->calculateTax($items);

        return [
            'success' => true,
            'error'   => null,
            'data'    => $calc,
        ];
    }

    /**
     * Generate an invoice from one or more draft billing items.
     *
     * @param  array<int> $billingItemIds  IDs of billing_items to include.
     * @param  int        $createdByUserId  User ID from JWT (passed by controller).
     * @param  string     $notes           Optional invoice notes.
     * @return array  ['success' => bool, 'data' => invoice|null, 'error' => string|null]
     */
    public function generateInvoice(array $billingItemIds, int $createdByUserId, string $notes = ''): array
    {
        // ── 1. Load and validate items ────────────────────────────────────────
        $ownershipId = null;
        $errors      = [];
        $items       = $this->loadAndValidateItems($billingItemIds, $ownershipId, $errors);

        if (!empty($errors)) {
            return ['success' => false, 'error' => $errors[0], 'data' => null];
        }

        // ── 2. Resolve company (auto-select first active) ─────────────────────
        $company = $this->companyModel->first();
        if (!$company) {
            return ['success' => false, 'error' => 'Tidak ada data perusahaan. Tambahkan perusahaan terlebih dahulu.', 'data' => null];
        }

        // ── 3. Resolve customer_id from the first item's ownership ────────────
        $ownership = $this->billingItemModel->db->table('ownerships')
            ->where('id', $ownershipId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$ownership) {
            return ['success' => false, 'error' => 'Data kepemilikan tidak ditemukan.', 'data' => null];
        }

        // ── 4. Calculate tax ──────────────────────────────────────────────────
        $calc     = $this->calculateTax($items);
        $issueDate = date('Y-m-d');

        // ── 5. Execute inside a database transaction ──────────────────────────
        $db = Database::connect();
        $db->transStart();

        try {
            // 5a. Generate invoice number (SELECT FOR UPDATE on system_settings)
            $invoiceNumber = $this->generateInvoiceNumber($db, $issueDate);

            // 5b. Determine due date from system_settings
            $offsetDays = (int)($this->getSetting($db, 'invoice_due_date_offset_days') ?? 14);
            $dueDate    = date('Y-m-d', strtotime("+{$offsetDays} days", strtotime($issueDate)));

            // 5c. Insert invoice header
            $invoiceData = [
                'invoice_number'  => $invoiceNumber,
                'company_id'      => (int)$company['id'],
                'ownership_id'    => $ownershipId,
                'customer_id'     => (int)$ownership['customer_id'],
                'created_by'      => $createdByUserId,
                'issue_date'      => $issueDate,
                'due_date'        => $dueDate,
                'subtotal_dpp'    => $calc['subtotal_dpp'],
                'dpp_nilai_lain'  => $calc['dpp_nilai_lain'],
                'ppn_amount'      => $calc['ppn_amount'],
                'grand_total'     => $calc['grand_total'],
                'tax_applied'     => $calc['tax_applied'] ? 1 : 0,
                'ppn_rate'        => 12.00,
                'notes'           => $notes,
                'status'          => 'draft',
            ];

            $invoiceId = $db->table('invoices')->insert($invoiceData, true);

            if (!$invoiceId) {
                throw new \RuntimeException('Gagal menyimpan header invoice.');
            }

            // 5d. Insert immutable snapshot rows into invoice_items
            $now          = date('Y-m-d H:i:s');
            $snapshotRows = [];

            foreach ($items as $item) {
                $snapshotRows[] = [
                    'invoice_id'            => $invoiceId,
                    'billing_item_id'       => (int)$item['id'],
                    'billing_type'          => $item['billing_type'],
                    'billing_period_start'  => $item['billing_period_start'],
                    'billing_period_end'    => $item['billing_period_end'],
                    'description'           => $item['description'],
                    'quantity'              => $item['quantity'],
                    'unit'                  => $item['unit'],
                    'unit_price'            => $item['unit_price'],
                    'subtotal'              => $item['subtotal'],
                    'management_fee_rate'   => $item['management_fee_rate'] ?? null,
                    'management_fee_amount' => $item['management_fee_amount'] ?? null,
                    'pln_charge'            => $item['pln_charge'] ?? null,
                    'apply_tax'             => (int)$item['apply_tax'],
                    'notes'                 => $item['notes'] ?? null,
                    'created_at'            => $now,
                ];
            }

            $db->table('invoice_items')->insertBatch($snapshotRows);

            // 5e. Mark original billing_items as 'invoiced'
            $billingItemIds = array_column($items, 'id');
            $db->table('billing_items')
               ->whereIn('id', $billingItemIds)
               ->update(['status' => 'invoiced', 'updated_at' => $now]);

            $db->transComplete();

        } catch (\Throwable $e) {
            $db->transRollback();
            return [
                'success' => false,
                'error'   => 'Terjadi kesalahan saat generate invoice: ' . $e->getMessage(),
                'data'    => null,
            ];
        }

        if ($db->transStatus() === false) {
            return ['success' => false, 'error' => 'Transaksi database gagal. Silakan coba lagi.', 'data' => null];
        }

        // ── 6. Return enriched invoice ────────────────────────────────────────
        $invoice = $this->invoiceModel->findWithRelations((int)$invoiceId);

        return [
            'success' => true,
            'error'   => null,
            'data'    => $invoice,
        ];
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    /**
     * Load billing items by ID, validate they are all draft and same ownership.
     *
     * @param  array<int>  $ids         Input IDs.
     * @param  int|null   &$ownershipId Out — resolved ownership_id.
     * @param  array      &$errors      Out — validation error messages.
     * @return array<int, array>        Loaded item rows (plain arrays).
     */
    private function loadAndValidateItems(array $ids, ?int &$ownershipId, array &$errors): array
    {
        $errors      = [];
        $ownershipId = null;

        if (empty($ids)) {
            $errors[] = 'Tidak ada item tagihan yang dipilih.';
            return [];
        }

        $db    = Database::connect();
        $items = $db->table('billing_items')
            ->whereIn('id', $ids)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        if (count($items) !== count($ids)) {
            $errors[] = 'Satu atau lebih item tagihan tidak ditemukan.';
            return [];
        }

        $ownershipIds = array_unique(array_column($items, 'ownership_id'));
        if (count($ownershipIds) > 1) {
            $errors[] = 'Semua item tagihan harus dari kepemilikan (ownership) yang sama.';
            return [];
        }

        foreach ($items as $item) {
            if ($item['status'] !== 'draft') {
                $errors[] = "Item tagihan #{$item['id']} bukan berstatus draft (status saat ini: {$item['status']}).";
                return [];
            }
        }

        $ownershipId = (int)$ownershipIds[0];
        return $items;
    }

    /**
     * Calculate tax fields from a set of billing item rows.
     *
     * Returns:
     * [
     *   'subtotal_taxable'    => float,
     *   'subtotal_nontaxable' => float,
     *   'subtotal_dpp'        => float,   // taxable + non-taxable
     *   'dpp_nilai_lain'      => float,   // (11/12) × subtotal_taxable
     *   'ppn_amount'          => float,   // 12% × dpp_nilai_lain
     *   'grand_total'         => float,   // subtotal_dpp + ppn_amount
     *   'tax_applied'         => bool,
     * ]
     *
     * @param  array<int, array> $items
     * @return array
     */
    private function calculateTax(array $items): array
    {
        $subtotalTaxable    = 0.0;
        $subtotalNontaxable = 0.0;

        foreach ($items as $item) {
            $subtotal = (float)($item['subtotal'] ?? 0);
            if ((int)$item['apply_tax'] === 1) {
                $subtotalTaxable += $subtotal;
            } else {
                $subtotalNontaxable += $subtotal;
            }
        }

        $subtotalDpp   = $subtotalTaxable + $subtotalNontaxable;
        $dppNilaiLain  = round((11 / 12) * $subtotalTaxable, 2);
        $ppnAmount     = round(0.12 * $dppNilaiLain, 2);
        $grandTotal    = round($subtotalDpp + $ppnAmount, 2);
        $taxApplied    = $subtotalTaxable > 0;

        return [
            'subtotal_taxable'    => round($subtotalTaxable, 2),
            'subtotal_nontaxable' => round($subtotalNontaxable, 2),
            'subtotal_dpp'        => round($subtotalDpp, 2),
            'dpp_nilai_lain'      => $dppNilaiLain,
            'ppn_amount'          => $ppnAmount,
            'grand_total'         => $grandTotal,
            'tax_applied'         => $taxApplied,
        ];
    }

    /**
     * Generate a sequential, race-condition-safe invoice number.
     *
     * Uses SELECT … FOR UPDATE on system_settings to lock the counter row
     * for the current month. If no row exists yet, it is inserted atomically.
     *
     * Format: INV/YYYY/MM/NNNN  (e.g. INV/2026/08/0001)
     *
     * MUST be called inside an active transaction.
     *
     * @param  \CodeIgniter\Database\BaseConnection $db
     * @param  string $issueDate  'Y-m-d'
     * @return string
     */
    private function generateInvoiceNumber($db, string $issueDate): string
    {
        $year  = date('Y', strtotime($issueDate));
        $month = date('m', strtotime($issueDate));
        $key   = "invoice_sequence_{$year}_{$month}";

        // Lock the counter row for this month (prevents duplicate numbers under concurrency)
        $row = $db->query(
            "SELECT id, value FROM system_settings WHERE `key` = ? FOR UPDATE",
            [$key]
        )->getRowArray();

        if ($row) {
            $next = (int)$row['value'] + 1;
            $db->table('system_settings')
               ->where('id', (int)$row['id'])
               ->update([
                   'value'      => (string)$next,
                   'updated_at' => date('Y-m-d H:i:s'),
               ]);
        } else {
            $next = 1;
            $db->table('system_settings')->insert([
                'key'         => $key,
                'value'       => '1',
                'description' => "Invoice sequence counter for {$year}/{$month}",
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return sprintf('INV/%s/%s/%04d', $year, $month, $next);
    }

    /**
     * Read a single value from system_settings by key.
     *
     * @param  \CodeIgniter\Database\BaseConnection $db
     * @param  string $key
     * @return string|null
     */
    private function getSetting($db, string $key): ?string
    {
        $row = $db->table('system_settings')
            ->where('key', $key)
            ->get()
            ->getRowArray();

        return $row ? $row['value'] : null;
    }
}
