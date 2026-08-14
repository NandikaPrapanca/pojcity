<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->setJSON([
                'success' => false,
                'data'    => null,
                'message' => 'Unauthorized. Token required.',
                'errors'  => null,
            ])->setStatusCode(401);
        }

        $token = substr($authHeader, 7);

        try {
            $secret  = $_ENV['JWT_SECRET'] ?? env('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            // Attach decoded payload to request for downstream use
            $request->jwtPayload = $decoded;
        } catch (\Exception $e) {
            return response()->setJSON([
                'success' => false,
                'data'    => null,
                'message' => 'Unauthorized. Invalid or expired token.',
                'errors'  => $e->getMessage(),
            ])->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
