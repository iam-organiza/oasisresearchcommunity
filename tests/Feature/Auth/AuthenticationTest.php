<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\LoginLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logging_out_from_a_react_page_is_a_full_page_visit()
    {
        // The home page is a Blade page; an in-app redirect would show it in Inertia's pop-up.
        $version = (new HandleInertiaRequests)->version(request());

        $this->actingAs(User::factory()->create())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
            ->post(route('logout'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }

    public function test_five_wrong_passwords_lock_the_account_for_fifteen_minutes()
    {
        $user = User::factory()->create();
        $attempt = fn (string $password) => $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        // The starter's per-minute limit is separate; keep it out of the way.
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach (range(1, 2) as $try) {
            $attempt('wrong-password')->assertSessionHasErrors(['email' => 'That email and password do not match.']);
        }
        // A warning while there is still time to reset instead.
        $attempt('wrong-password')->assertSessionHasErrors([
            'email' => 'That email and password do not match. Two more wrong tries lock the account for 15 minutes.',
        ]);
        $attempt('wrong-password')->assertSessionHasErrors([
            'email' => 'That email and password do not match. One more wrong try locks the account for 15 minutes.',
        ]);
        $attempt('wrong-password')->assertSessionHasErrors([
            'email' => 'That is five wrong passwords, so the account is locked for 15 minutes. To get in now, reset your password.',
        ]);

        // Even the right password is refused while locked.
        $attempt('password')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(16)->minutes();
        $attempt('password')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_correct_password_resets_the_wrong_password_count()
    {
        $user = User::factory()->create();
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach (range(1, 4) as $try) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_an_unconfirmed_account_lands_on_check_your_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('admin'))->assertRedirect(route('verification.notice'));
    }

    public function test_the_locked_message_points_to_resetting_the_password(): void
    {
        $user = User::factory()->create();
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (range(1, LoginLockout::ATTEMPTS) as $try) {
            LoginLockout::miss($user->email);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account is locked for 15 minutes after too many wrong passwords. To get in now, reset your password.']);
    }
}
