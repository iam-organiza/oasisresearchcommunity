<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
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
