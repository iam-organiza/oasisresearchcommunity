<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\PasswordRequirements;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'first_name.required' => 'Enter your first name.',
            'last_name.required' => 'Enter your last name.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter an email address like name@example.com.',
            // The sign-up page offers "Log in" and "Reset your password" under this one.
            'email.unique' => 'There is already an account with this email.',
            ...PasswordRequirements::messages(),
            'terms.accepted' => 'Agree to the Terms & Conditions to create an account.',
        ])->validate();

        $user = new User([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->terms_accepted_at = now();
        $user->save();

        return $user;
    }
}
