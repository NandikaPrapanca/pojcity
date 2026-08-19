<?php

namespace App\Controllers\Api;

use App\Services\MeterReadingService;

class MeterReadingController extends BaseApiController
{
    protected MeterReadingService $service;

    public function __construct()
    {
        $this->service = new MeterReadingService();
    }

    /**
     * GET /api/v1/meter-readings
     */
    public function index()
    {
        $filters = [
            'ownership_id' => $this->request->getGet('ownership_id') ?? '',
            'period'       => $this->request->getGet('period') ?? '',
            'search'       => $this->request->getGet('search') ?? '',
        ];

        $filters = array_filter($filters, fn($v) => $v !== '');

        return $this->success($this->service->getAll($filters));
    }

    /**
     * GET /api/v1/meter-readings/{id}
     */
    public function show(int $id)
    {
        $reading = $this->service->getById($id);
        if (!$reading) return $this->notFound('Catatan meteran tidak ditemukan.');
        return $this->success($reading);
    }

    /**
     * GET /api/v1/ownerships/{id}/meter-readings
     */
    public function forOwnership(int $id)
    {
        return $this->success($this->service->getForOwnership($id));
    }

    /**
     * GET /api/v1/ownerships/{id}/meter-readings/latest
     */
    public function latest(int $id)
    {
        $latest = $this->service->getLatest($id);
        return $this->success($latest);
    }

    /**
     * POST /api/v1/meter-readings
     */
    public function create()
    {
        // Support both multipart form data and JSON payload
        $body = $this->request->getPost();
        if (empty($body)) {
            $body = $this->getBody();
        }

        $rules = [
            'ownership_id'         => 'required|integer',
            'meter_number'         => 'permit_empty|max_length[100]',
            'reading_date'         => 'required|valid_date',
            'previous_reading'     => 'required|decimal',
            'current_reading'      => 'required|decimal',
            'billing_period_start' => 'required|valid_date',
            'billing_period_end'   => 'required|valid_date',
            'notes'                => 'permit_empty',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $file = $this->request->getFile('photo');
        $validFile = ($file && $file->isValid() && !$file->hasMoved()) ? $file : null;

        $result = $this->service->create($body, $validFile);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Catatan meteran berhasil disimpan.', 201);
    }

    /**
     * PUT/POST /api/v1/meter-readings/{id}
     */
    public function update(int $id)
    {
        $reading = $this->service->getById($id);
        if (!$reading) return $this->notFound('Catatan meteran tidak ditemukan.');

        $body = $this->request->getPost();
        if (empty($body)) {
            $body = $this->getBody();
        }

        $rules = [
            'ownership_id'         => 'permit_empty|integer',
            'meter_number'         => 'permit_empty|max_length[100]',
            'reading_date'         => 'permit_empty|valid_date',
            'previous_reading'     => 'permit_empty|decimal',
            'current_reading'      => 'permit_empty|decimal',
            'billing_period_start' => 'permit_empty|valid_date',
            'billing_period_end'   => 'permit_empty|valid_date',
            'notes'                => 'permit_empty',
        ];

        if (!$this->validateData($body, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $file = $this->request->getFile('photo');
        $validFile = ($file && $file->isValid() && !$file->hasMoved()) ? $file : null;

        $result = $this->service->update($id, $body, $validFile);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result['data'], 'Catatan meteran berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/meter-readings/{id}
     */
    public function delete(int $id)
    {
        $reading = $this->service->getById($id);
        if (!$reading) return $this->notFound('Catatan meteran tidak ditemukan.');

        $this->service->delete($id);
        return $this->success(null, 'Catatan meteran berhasil dihapus.');
    }

    /**
     * GET /api/v1/meter-readings/photo/(:any)
     * Serve uploaded photo securely with appropriate MIME header.
     */
    public function servePhoto(string $fileName)
    {
        $safeName = basename($fileName);
        $filePath = WRITEPATH . 'uploads/meter-photos/' . $safeName;

        if (!file_exists($filePath)) {
            return $this->notFound('Foto tidak ditemukan.');
        }

        $mime = mime_content_type($filePath) ?: 'image/jpeg';
        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setBody(file_get_contents($filePath));
    }
}
