<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

/**
 * @property int $id
 * @property string $user_ref
 * @property string $first_name
 * @property string $last_name
 * @property-read string $name
 * @property UserRole $role
 * @property-read Profile|null $profile
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property CarbonInterface|null $terms_accepted_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $newsletter_opted_out_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Appends(['name'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * New accounts are ordinary members until staff say otherwise.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'member',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'newsletter_opted_out_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->user_ref ??= (string) Str::uuid();
        });
    }

    /**
     * The reset email goes out once the "Forgot password" page has answered,
     * so the answer comes as quickly for a member's email as for an unknown
     * one and its timing does not show who has an account.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        defer(fn () => $this->notify(new ResetPassword($token)));
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<MembershipApplication, $this>
     */
    public function membershipApplications(): HasMany
    {
        return $this->hasMany(MembershipApplication::class);
    }

    /**
     * Their place on the member register, once approved.
     *
     * @return HasOne<Membership, $this>
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    /**
     * Fees on the person's account, oldest first.
     *
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<AccountEvent, $this>
     */
    public function accountEvents(): HasMany
    {
        return $this->hasMany(AccountEvent::class)->orderByDesc('id');
    }

    /**
     * The person's most recent membership application, if any.
     *
     * @return HasOne<MembershipApplication, $this>
     */
    public function latestApplication(): HasOne
    {
        return $this->hasOne(MembershipApplication::class)->latestOfMany();
    }

    /**
     * Full name, kept as one value for the places that show it.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn (): string => trim($this->first_name.' '.$this->last_name),
        );
    }
}
