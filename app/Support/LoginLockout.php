<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Five wrong passwords in a row lock an email for 15 minutes, as the first
 * site did. Counted for unknown addresses too, so the answers do not reveal
 * which emails have accounts. Resetting the password lifts the lock.
 */
final class LoginLockout
{
    public const ATTEMPTS = 5;

    public const SECONDS = 15 * 60;

    public static function key(string $email): string
    {
        return 'login-lockout:'.sha1(Str::lower($email));
    }

    public static function locked(string $email): bool
    {
        return RateLimiter::tooManyAttempts(self::key($email), self::ATTEMPTS);
    }

    /** Counts a wrong password and returns how many tries are left before the lock. */
    public static function miss(string $email): int
    {
        return max(0, self::ATTEMPTS - RateLimiter::hit(self::key($email), self::SECONDS));
    }

    public static function clear(string $email): void
    {
        RateLimiter::clear(self::key($email));
    }

    /** "12 minutes" or "1 minute", for the locked message. */
    public static function timeLeft(string $email): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn(self::key($email)) / 60));

        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
