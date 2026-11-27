<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('Test User', $user->name);
        $this->assertSame(UserRole::Member, $user->refresh()->role);
        $this->assertTrue(Str::isUuid($user->user_ref));
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_registration_needs_agreement_to_the_terms()
    {
        $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_the_page_lists_the_password_rules_before_anyone_types(): void
    {
        $this->get(route('register'))->assertInertia(fn ($page) => $page
            ->component('auth/register')
            ->where('passwordChecklist.0.label', 'At least 8 characters'));
    }

    public function test_an_email_with_an_account_gets_a_way_back_in(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), $this->details(['email' => 'taken@example.com']))
            ->assertSessionHasErrors(['email' => 'There is already an account with this email.']);
    }

    public function test_errors_are_in_the_sites_words(): void
    {
        $this->post(route('register.store'), $this->details([
            'first_name' => '',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]))->assertSessionHasErrors([
            'first_name' => 'Enter your first name.',
            'password' => 'Your password needs at least 8 characters.',
        ]);
    }

    public function test_one_connection_can_create_five_accounts_an_hour(): void
    {
        foreach (range(1, 5) as $n) {
            $this->post(route('register.store'), $this->details(['email' => "person{$n}@example.com"]))
                ->assertRedirect(route('verification.notice', absolute: false));
            auth()->logout();
        }

        $this->post(route('register.store'), $this->details(['email' => 'person6@example.com']))
            ->assertSessionHasErrors(['email' => 'Too many accounts have been created from this connection. Try again in an hour.']);
        $this->assertSame(5, User::count());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function details(array $overrides = []): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
            ...$overrides,
        ];
    }
}
