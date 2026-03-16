<?php

namespace App\Helpers;

class ResponseHelper
{
    private static bool $toCache = false;

    private static function send(array $payload, int $status)
    {
        header(self::$toCache ? 'Cache-Control: max-age=60' : 'Cache-Control: no-cache, no-store');
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private static function success(array $data = [], string $message = 'OK', int $status = 200): void
    {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private static function error(string $message = 'Something went wrong', array $errors = [], int $status = 400): void
    {
        self::send([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function cacheable(bool $cache = true): void
    {
        self::$toCache = $cache;
    }

    public static function ok(array $data = [], string $message = 'Ok'): void
    {
        self::success($data, $message, 200);
    }

    public static function locked(string $message = 'Locked', array $errors = []): void
    {
        self::error($message, $errors, 423);
    }

    public static function badRequest(string $message = 'Bad request', array $errors = []): void
    {
        self::error($message, $errors, 400);
    }

    public static function unauthorized(string $message = 'Unauthorized', array $errors = []): void
    {
        self::error($message, $errors, 401);
    }

    public static function forbidden(string $message = 'Forbidden', array $errors = []): void
    {
        self::error($message, $errors, 403);
    }

    public static function notFound(string $message = 'Not found', array $errors = []): void
    {
        self::error($message, $errors, 404);
    }

    public static function methodNotAllowed(string $message = 'Method not allowed', array $errors = []): void
    {
        self::error($message, $errors, 405);
    }

    public static function conflict(string $message = 'Conflict', array $errors = []): void
    {
        self::error($message, $errors, 409);
    }

    public static function unprocessable(string $message = 'Unprocessable entity', array $errors = []): void
    {
        self::error($message, $errors, 422);
    }

    public static function internalError(string $message = 'Internal server error', array $errors = []): void
    {
        self::error($message, $errors, 500);
    }

    public static function created(array $data = [], string $message = 'Created'): void
    {
        self::success($data, $message, 201);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}
