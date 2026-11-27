<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * What a new password must be, in one place: the rule the server checks,
 * the list the sign-up and reset forms show before anyone types, and the
 * messages when one is missed. Strict on the real sites; 8 characters
 * locally, as Laravel's default.
 */
final class PasswordRequirements
{
    public static function strict(): bool
    {
        return app()->isProduction();
    }

    public static function rule(): ?Password
    {
        return self::strict()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null;
    }

    /**
     * The checklist the forms show. `check` names a test the page can run as
     * people type; the breach check happens only when the form is sent.
     *
     * @return list<array{check: string, label: string}>
     */
    public static function checklist(): array
    {
        if (! self::strict()) {
            return [['check' => 'length:8', 'label' => 'At least 8 characters']];
        }

        return [
            ['check' => 'length:12', 'label' => 'At least 12 characters'],
            ['check' => 'mixed', 'label' => 'Upper and lower case letters'],
            ['check' => 'number', 'label' => 'At least one number'],
            ['check' => 'symbol', 'label' => 'At least one symbol, such as ! or #'],
            ['check' => 'breach', 'label' => 'Not a password that has appeared in a data breach'],
        ];
    }

    /**
     * Error messages for a password field, in the site's words.
     *
     * @return array<string, string>
     */
    public static function messages(string $field = 'password'): array
    {
        return [
            "{$field}.required" => 'Choose a password.',
            "{$field}.min" => 'Your password needs at least :min characters.',
            "{$field}.confirmed" => 'The two passwords do not match.',
            "{$field}.{$field}.mixed" => 'Your password needs upper and lower case letters.',
            "{$field}.{$field}.letters" => 'Your password needs at least one letter.',
            "{$field}.{$field}.numbers" => 'Your password needs at least one number.',
            "{$field}.{$field}.symbols" => 'Your password needs at least one symbol, such as ! or #.',
            "{$field}.{$field}.uncompromised" => 'This password has appeared in a data breach, so it is not safe. Choose a different one.',
        ];
    }
}
