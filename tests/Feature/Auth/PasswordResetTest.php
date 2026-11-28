<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\PasswordChanged;
use App\Support\LoginLockout;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
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

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('auth/reset-password')->where('email', $user->email));

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

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()->assertSessionHas('sentTo', $user->email);
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()->assertSessionHas('sentTo', 'nobody@example.com');
        // Asking again at once (throttled) gives the same answer too.
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()->assertSessionHas('sentTo', $user->email);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_after_sending_the_page_names_the_address_and_how_long_the_link_lasts(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'typo@gmail.con'])
            ->assertRedirect(route('password.request'));

        $this->get(route('password.request'))
            ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password')
                ->where('sentTo', 'typo@gmail.con')
                ->where('linkMinutes', 60));
    }

    public function test_the_email_typed_on_log_in_is_filled_in(): void
    {
        $this->get(route('password.request', ['email' => 'ada@example.com']))
            ->assertInertia(fn (Assert $page) => $page->where('email', 'ada@example.com'));
    }

    public function test_one_connection_cannot_make_the_site_email_a_list_of_addresses(): void
    {
        Notification::fake();

        foreach (range(1, 10) as $n) {
            $this->post(route('password.email'), ['email' => "person{$n}@example.com"])->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => 'person11@example.com'])
            ->assertSessionHasErrors(['email' => 'Too many reset links have been asked for from this connection. Try again in an hour.']);
    }

    public function test_the_reset_email_waits_until_the_page_has_answered(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withoutDefer();
        $user->sendPasswordResetNotification('a-token');
        Notification::assertSentTo($user, ResetPassword::class);

        Notification::fake();
        $this->withDefer();
        $user->sendPasswordResetNotification('a-token');
        Notification::assertNothingSent();
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

    public function test_a_dead_link_says_so_as_the_page_opens(): void
    {
        $user = User::factory()->create();

        $this->get(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/reset-link-expired')->where('email', $user->email));

        // An unknown address looks exactly the same.
        $this->get(route('password.reset', ['token' => 'invalid-token', 'email' => 'nobody@example.com']))
            ->assertInertia(fn (Assert $page) => $page->component('auth/reset-link-expired'));
    }

    public function test_a_reset_confirms_the_email_and_says_the_password_changed(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, PasswordChanged::class, fn (PasswordChanged $n) => $n->via === PasswordChanged::VIA_RESET);
    }

    public function test_the_password_changed_email_says_which_account_and_how(): void
    {
        $user = User::factory()->create(['first_name' => 'Ada']);

        $mail = (new PasswordChanged(PasswordChanged::VIA_SETTINGS, CarbonImmutable::parse('2026-11-28 14:05', 'Africa/Lagos')))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Your OASIS Research Community password was changed', $mail->subject);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString('28 Nov 2026, 2:05 pm', $html);
        $this->assertStringContainsString('changed in Settings, while logged in', $html);
        $this->assertStringContainsString(route('password.request'), $html);
    }

    public function test_a_logged_in_visitor_with_a_working_link_is_logged_out_to_use_it(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        $this->actingAs($user)->get($url)
            ->assertRedirect($url)
            ->assertSessionHas('status', 'We logged you out so you can choose a new password with this link.');
        $this->assertGuest();
    }

    public function test_a_logged_in_visitor_with_a_dead_link_stays_logged_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertRedirect(route('security.edit'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('password.request'))->assertRedirect(route('security.edit'));
    }

    public function test_a_logged_in_member_who_forgot_the_password_can_log_out_and_reset_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('password.forgotten'))
            ->assertRedirect(route('password.request', ['email' => $user->email]));
        $this->assertGuest();
    }

    public function test_the_email_is_by_name_and_says_how_long_the_link_lasts(): void
    {
        $user = User::factory()->create(['first_name' => 'Ada']);

        $mail = (new ResetPassword('a-token'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Reset your OASIS Research Community password', $mail->subject);
        $this->assertStringContainsString('Hello Ada,', $html);
        $this->assertStringContainsString('Choose a new password', $html);
        $this->assertStringContainsString('60 minutes, once', $html);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString(route('password.reset', ['token' => 'a-token', 'email' => $user->email]), $html);
    }
}
