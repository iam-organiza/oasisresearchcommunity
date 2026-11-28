<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * The password reset link: how long it lasts, and whether one still works.
 */
final class ResetLink
{
    public static function minutes(): int
    {
        return (int) config('auth.passwords.'.config('fortify.passwords', 'users').'.expire', 60);
    }

    /**
     * Whether the token still opens a reset for this email: not expired, not
     * used and not replaced by a newer link.
     */
    public static function isValid(string $token, string $email): bool
    {
        if ($token === '' || $email === '') {
            return false;
        }

        $user = User::where('email', $email)->first();

        return $user !== null && self::broker()->tokenExists($user, $token);
    }

    private static function broker(): PasswordBroker
    {
        /** @var PasswordBroker */
        return Password::broker(config('fortify.passwords', 'users'));
    }
}
