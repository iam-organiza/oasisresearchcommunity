<?php

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Helpers\ResponseHelper;

class Auth
{
    private static function getKey(): string
    {
        return $_ENV['JWT_SECRET'] ?? throw new \Exception('JWT secret not defined');
    }

    public static function generateToken(array $payload, int $duration = 3600)
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $duration;

        return JWT::encode($payload, self::getKey(), 'HS256');
    }

    public static function validateToken($token)
    {
        try {
            return JWT::decode($token, new Key(self::getKey(), 'HS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            ResponseHelper::unauthorized('Session expired, please log in again', []);
        } catch (\Exception $e) {
            ResponseHelper::unauthorized('Invalid token', []);
        }
    }
}
