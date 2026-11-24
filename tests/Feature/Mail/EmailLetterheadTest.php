<?php

namespace Tests\Feature\Mail;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailLetterheadTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_emails_use_the_oasis_letterhead(): void
    {
        $user = User::factory()->create();

        foreach ([
            (new VerifyEmail)->toMail($user),
            (new ResetPassword('token'))->toMail($user),
        ] as $mail) {
            $html = (string) $mail->render();

            // Navy band with the white logo, the crimson button, and the secretariat's details.
            $this->assertStringContainsString(asset('images/oasis-logo-white.png'), $html);
            $this->assertStringContainsString('background-color: #080250', $html);
            $this->assertStringContainsString('background-color: #c8003f', $html);
            $this->assertStringContainsString(config('oasis.support_email'), $html);
            $this->assertStringContainsString(route('terms'), $html);
            $this->assertStringNotContainsString('laravel.com', $html);
        }
    }
}
