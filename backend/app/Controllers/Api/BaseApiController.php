<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected function success($data = null, string $message = 'OK', int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => true,
            'data'    => $data,
            'message' => $message,
            'errors'  => null,
        ]);
    }

    protected function error(string $message, $errors = null, int $status = 400): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ]);
    }

    protected function notFound(string $message = 'Data tidak ditemukan.'): ResponseInterface
    {
        return $this->error($message, null, 404);
    }

    protected function validationError(array $errors): ResponseInterface
    {
        return $this->error('Validasi gagal.', $errors, 422);
    }

    protected function getBody(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
    }
}
