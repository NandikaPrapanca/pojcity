<?php

namespace App\Services;

use App\Models\UserModel;
use App\Models\RoleModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    protected UserModel $userModel;
    protected RoleModel $roleModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
    }

    public function attemptLogin(string $email, string $password): array
    {
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            return ['success' => false, 'message' => 'Email atau password salah.'];
        }

        if (!(bool)$user['is_active']) {
            return ['success' => false, 'message' => 'Akun tidak aktif.'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Email atau password salah.'];
        }

        // Update last login
        $this->userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        $role = $this->roleModel->find($user['role_id']);

        $token = $this->generateToken($user, $role);

        return [
            'success' => true,
            'token'   => $token,
            'user'    => $this->sanitizeUser($user, $role),
        ];
    }

    public function getUserFromToken(string $token): ?array
    {
        try {
            $secret  = env('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $userId  = $decoded->sub ?? null;

            if (!$userId) {
                return null;
            }

            $user = $this->userModel->findActiveById((int)$userId);
            if (!$user) {
                return null;
            }

            $role = $this->roleModel->find($user['role_id']);

            return $this->sanitizeUser($user, $role);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateToken(array $user, ?array $role): string
    {
        $secret  = env('JWT_SECRET');
        $expire  = (int)(env('JWT_EXPIRE') ?: 86400);

        $payload = [
            'iss'  => env('app.baseURL') ?: 'http://localhost:8080',
            'sub'  => $user['id'],
            'iat'  => time(),
            'exp'  => time() + $expire,
            'role' => $role['name'] ?? 'admin',
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private function sanitizeUser(array $user, ?array $role): array
    {
        return [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $role['name'] ?? null,
        ];
    }
}
