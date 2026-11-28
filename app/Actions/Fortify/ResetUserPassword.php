<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Notifications\Auth\PasswordChanged;
use App\Support\LoginLockout;
use App\Support\PasswordRequirements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

use function Illuminate\Support\defer;

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

        // The link came to their inbox, so the address is theirs: no separate confirming step after.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $changedAt = CarbonImmutable::now();
        defer(fn () => $user->notify(new PasswordChanged(PasswordChanged::VIA_RESET, $changedAt)));
    }
}
