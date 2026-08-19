<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    protected AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(): ResponseInterface
    {
        // Support both JSON and form-encoded bodies
        $body     = $this->request->getJSON(true) ?? $this->request->getPost();
        $email    = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        $data = ['email' => $email, 'password' => $password];

        if (!$this->validateData($data, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'data'    => null,
                'message' => 'Validasi gagal.',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $result = $this->authService->attemptLogin($email, $password);

        if (!$result['success']) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'data'    => null,
                'message' => $result['message'],
                'errors'  => null,
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'data'    => [
                'token' => $result['token'],
                'user'  => $result['user'],
            ],
            'message' => 'Login berhasil.',
            'errors'  => null,
        ]);
    }

    /**
     * GET /api/v1/auth/me
     * Requires AuthFilter
     */
    public function me(): ResponseInterface
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $token      = substr($authHeader, 7);

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'data'    => null,
                'message' => 'Tidak terautentikasi.',
                'errors'  => null,
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'data'    => ['user' => $user],
            'message' => 'OK',
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * JWT is stateless — client deletes the token.
     */
    public function logout(): ResponseInterface
    {
        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'data'    => null,
            'message' => 'Logout berhasil.',
            'errors'  => null,
        ]);
    }
}
