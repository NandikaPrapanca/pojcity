<?php

namespace App\Controllers\Api;

use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use Config\Database;

/**
 * PaymentController
 *
 * Routes (protected by 'auth' filter):
 *   POST /api/v1/payments                     → create()
 *   GET  /api/v1/payments/invoice/{invoiceId} → getForInvoice($invoiceId)
 */
class PaymentController extends BaseApiController
{
    protected PaymentModel $paymentModel;
    protected InvoiceModel $invoiceModel;

    public function __construct()
    {
        $this->paymentModel = new PaymentModel();
        $this->invoiceModel = new InvoiceModel();
    }

    /**
     * POST /api/v1/payments
     *
     * Records a payment transaction against an invoice, and automatically
     * transitions the invoice status to 'paid'.
     */
    public function create()
    {
        $body = $this->getBody();

        $rules = [
            'invoice_id'       => 'required|integer',
            'amount'           => 'required|decimal|greater_than[0]',
            'payment_method'   => 'required|max_length[100]',
            'payment_date'     => 'required|valid_date',
            'reference_number' => 'permit_empty|max_length[100]',
            'notes'            => 'permit_empty|max_length[500]',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $invoiceId = (int)$body['invoice_id'];
        $invoice   = $this->invoiceModel->find($invoiceId);

        if (!$invoice || $invoice['deleted_at'] !== null) {
            return $this->notFound('Invoice tidak ditemukan.');
        }

        if ($invoice['status'] === 'paid') {
            return $this->error('Invoice ini sudah lunas.', null, 422);
        }

        if ($invoice['status'] === 'cancelled') {
            return $this->error('Invoice yang telah dibatalkan tidak dapat dibayar.', null, 422);
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $paymentData = [
                'invoice_id'       => $invoiceId,
                'amount'           => (float)$body['amount'],
                'payment_method'   => trim($body['payment_method']),
                'payment_date'     => $body['payment_date'],
                'reference_number' => !empty($body['reference_number']) ? trim($body['reference_number']) : null,
                'notes'            => !empty($body['notes']) ? trim($body['notes']) : null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            $paymentId = $this->paymentModel->insert($paymentData, true);

            // Update invoice status to 'paid'
            $this->invoiceModel->update($invoiceId, [
                'status'     => 'paid',
                'updated_at' => $now,
            ]);

            if ($db->transStatus() === false) {
                $db->transRollback();
                return $this->error('Gagal mencatat pembayaran.', null, 500);
            }

            $db->transCommit();

            $payment = $this->paymentModel->find((int)$paymentId);
            $updatedInvoice = $this->invoiceModel->findWithRelations($invoiceId);

            return $this->success([
                'payment' => $payment,
                'invoice' => $updatedInvoice,
            ], 'Pembayaran berhasil dicatat. Invoice telah lunas.', 201);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->error('Terjadi kesalahan: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/v1/payments/invoice/{invoiceId}
     */
    public function getForInvoice(int $invoiceId)
    {
        $payment = $this->paymentModel->getForInvoice($invoiceId);
        if (!$payment) {
            return $this->notFound('Belum ada data pembayaran untuk invoice ini.');
        }
        return $this->success($payment);
    }
}
