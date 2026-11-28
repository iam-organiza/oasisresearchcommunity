<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\LoginLockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_answers_the_same_for_any_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $neutral = 'If there is an account with that email, we have sent it a link to reset the password. The link works for 60 minutes.';

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()->assertSessionHas('status', $neutral);
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', $neutral);
        // Asking again at once (throttled) gives the same answer too.
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()->assertSessionHas('status', $neutral);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_a_new_password_lifts_a_lockout_and_returns_to_log_in_with_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        foreach (range(1, LoginLockout::ATTEMPTS) as $try) {
            LoginLockout::miss($user->email);
        }
        $this->assertTrue(LoginLockout::locked($user->email));

        $this->post(route('password.email'), ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('login'))
                ->assertSessionHas('status', 'Your password has been changed. Log in with the new one.')
                ->assertSessionHas('email', $user->email);

            return true;
        });

        $this->assertFalse(LoginLockout::locked($user->email));
    }

    public function test_a_changed_password_signs_out_other_sessions(): void
    {
        $user = User::factory()->create();

        // This session remembers the password it logged in with...
        $this->actingAs($user)->get(route('membership.show'))->assertOk();

        // ...so once the password changes somewhere else, it is signed out.
        $user->forceFill(['password' => 'changed-elsewhere'])->save();
        $this->get(route('membership.show'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_expired_or_used_link_says_so(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors(['email' => 'This reset link has expired or has already been used.']);
    }

    public function test_the_email_is_by_name_and_says_how_long_the_link_lasts(): void
    {
        $user = User::factory()->create(['first_name' => 'Ada']);

        $mail = (new ResetPassword('a-token'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Reset your OASIS Research Community password', $mail->subject);
        $this->assertStringContainsString('Hello Ada,', $html);
        $this->assertStringContainsString('Choose a new password', $html);
        $this->assertStringContainsString('The link works for 60 minutes', $html);
        $this->assertStringContainsString(route('password.reset', ['token' => 'a-token', 'email' => $user->email]), $html);
    }
}
