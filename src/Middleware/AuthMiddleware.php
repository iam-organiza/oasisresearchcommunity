<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Helpers\ResponseHelper;

class AuthMiddleware
{
    public static function handle()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? null;

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ResponseHelper::unauthorized('Unauthorized', []);
        }

        $token = $matches[1];
        $decoded = Auth::validateToken($token);

        if (!$decoded) {
            ResponseHelper::unauthorized('Invalid token', []);
        }

        return $decoded;
    }
}
