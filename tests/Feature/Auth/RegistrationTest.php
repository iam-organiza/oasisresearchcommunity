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
        $response->assertRedirect(route('admin', absolute: false));

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
}
