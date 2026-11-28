<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Support\LoginLockout;
use App\Support\PasswordRequirements;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ], PasswordRequirements::messages())->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        // A new password lifts a lockout: resetting is how a locked-out person gets back in.
        // Other devices are signed out by AuthenticateSession, which sees the password change.
        LoginLockout::clear($user->email);
    }
}
