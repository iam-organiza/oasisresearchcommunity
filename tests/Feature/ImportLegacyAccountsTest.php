<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportLegacyAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const REF = '6a1c7a52-6d0b-4b8e-9d55-0e2f6f1a9c01';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
        DB::purge('legacy');

        // The two tables as the first site defines them.
        Schema::connection('legacy')->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_ref', 36)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('verification_code', 10)->nullable();
            $table->boolean('verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->integer('login_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->string('role')->default('member');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::connection('legacy')->create('profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_ref', 36)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 20)->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function legacyMember(array $user = []): void
    {
        $user += [
            'user_ref' => self::REF,
            'email' => 'Halima@Example.com',
            // What the first site stored: PHP's default bcrypt at cost 10.
            'password' => password_hash('old-site-password', PASSWORD_BCRYPT, ['cost' => 10]),
            'verified' => 1,
            'verified_at' => '2026-03-18 09:30:00',
            'role' => 'admin',
            'created_at' => '2026-03-17 08:00:00',
        ];

        DB::connection('legacy')->table('users')->insert($user);
        DB::connection('legacy')->table('profiles')->insert([
            'user_ref' => $user['user_ref'],
            'first_name' => 'Halima',
            'last_name' => 'Bello',
            'phone' => '08030000000',
            'dob' => null,
            'address' => 'Wuse 2, Abuja',
        ]);
    }

    public function test_it_copies_accounts_and_profiles(): void
    {
        $this->legacyMember();

        $this->artisan('orc:import-legacy-accounts')->assertSuccessful();

        $user = User::where('user_ref', self::REF)->firstOrFail();
        $this->assertSame('halima@example.com', $user->email);
        $this->assertSame('Halima Bello', $user->name);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame('2026-03-18 09:30:00', $user->email_verified_at?->toDateTimeString());
        $this->assertSame('2026-03-17 08:00:00', $user->created_at?->toDateTimeString());
        $this->assertSame('08030000000', $user->profile?->phone);
        $this->assertSame('Wuse 2, Abuja', $user->profile?->address);
    }

    public function test_an_imported_member_logs_in_with_the_old_password(): void
    {
        $this->legacyMember();
        $this->artisan('orc:import-legacy-accounts')->assertSuccessful();

        $this->post(route('login.store'), [
            'email' => 'halima@example.com',
            'password' => 'old-site-password',
        ])->assertRedirect(route('admin', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_unverified_members_must_verify_again(): void
    {
        $this->legacyMember(['verified' => 0, 'verified_at' => null]);

        $this->artisan('orc:import-legacy-accounts')->assertSuccessful();

        $this->assertNull(User::where('user_ref', self::REF)->firstOrFail()->email_verified_at);
    }

    public function test_running_twice_updates_without_duplicating_or_touching_passwords(): void
    {
        $this->legacyMember();
        $this->artisan('orc:import-legacy-accounts')->assertSuccessful();

        $user = User::where('user_ref', self::REF)->firstOrFail();
        $user->forceFill(['password' => 'changed-on-the-new-site'])->save();
        $newHash = $user->refresh()->password;

        DB::connection('legacy')->table('profiles')->update(['last_name' => 'Bello-Okafor']);
        $this->artisan('orc:import-legacy-accounts')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('profiles')->count());
        $this->assertSame('Bello-Okafor', $user->refresh()->last_name);
        $this->assertSame($newHash, $user->password);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->legacyMember();

        $this->artisan('orc:import-legacy-accounts --dry-run')->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_an_address_taken_by_another_account_is_skipped(): void
    {
        User::factory()->create(['email' => 'halima@example.com']);
        $this->legacyMember();

        $this->artisan('orc:import-legacy-accounts')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertNull(User::where('user_ref', self::REF)->first());
    }
}
