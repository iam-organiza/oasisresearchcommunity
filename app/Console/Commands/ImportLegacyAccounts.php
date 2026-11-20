<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

#[Signature('orc:import-legacy-accounts
    {--dry-run : Report what would happen without writing anything}
    {--overwrite-passwords : Also replace the password of accounts that already exist here}')]
#[Description('Copy users and profiles from the first OASIS site, keeping their passwords')]
class ImportLegacyAccounts extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwritePasswords = (bool) $this->option('overwrite-passwords');

        $rows = DB::connection('legacy')
            ->table('users')
            ->leftJoin('profiles', 'profiles.user_ref', '=', 'users.user_ref')
            ->orderBy('users.id')
            ->get([
                'users.user_ref', 'users.email', 'users.password', 'users.verified',
                'users.verified_at', 'users.role', 'users.created_at',
                'profiles.first_name', 'profiles.last_name', 'profiles.phone',
                'profiles.dob', 'profiles.address',
            ]);

        $created = $updated = 0;
        $skipped = [];

        foreach ($rows as $row) {
            $email = Str::lower(trim((string) $row->email));
            $existing = DB::table('users')->where('user_ref', $row->user_ref)->first();

            // An address already used by a different account here cannot be
            // merged safely, so it is reported and left for a person to settle.
            $clash = DB::table('users')
                ->where('email', $email)
                ->where(fn ($q) => $q->whereNull('user_ref')->orWhere('user_ref', '!=', $row->user_ref))
                ->exists();

            if ($clash) {
                $skipped[] = [$email, 'email already belongs to another account'];

                continue;
            }

            $existing ? $updated++ : $created++;

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($row, $email, $existing, $overwritePasswords): void {
                $userId = $this->saveUser($row, $email, $existing, $overwritePasswords);
                $this->saveProfile($row, $userId);
            });
        }

        $this->components->info(($dryRun ? 'Dry run. ' : '').sprintf(
            '%d legacy accounts read: %d %s, %d %s, %d skipped.',
            $rows->count(),
            $created, $dryRun ? 'to create' : 'created',
            $updated, $dryRun ? 'to update' : 'updated',
            count($skipped),
        ));

        if ($skipped !== []) {
            $this->table(['Email', 'Reason'], $skipped);
        }

        return self::SUCCESS;
    }

    private function saveUser(stdClass $row, string $email, ?stdClass $existing, bool $overwritePasswords): int
    {
        $createdAt = Carbon::parse($row->created_at);

        $values = [
            'first_name' => trim((string) $row->first_name),
            'last_name' => trim((string) $row->last_name),
            'email' => $email,
            'role' => (UserRole::tryFrom((string) $row->role) ?? UserRole::Member)->value,
            // The first site confirmed addresses with an emailed code. Members
            // who never finished that step are asked to verify again here.
            'email_verified_at' => $row->verified
                ? Carbon::parse($row->verified_at ?? $row->created_at)
                : null,
            'updated_at' => now(),
        ];

        // Written through the query builder on purpose: the stored value is
        // already a bcrypt hash and must reach the table unchanged.
        if ($existing === null) {
            return (int) DB::table('users')->insertGetId($values + [
                'user_ref' => $row->user_ref,
                'password' => $row->password,
                // The first site also required agreement at sign-up.
                'terms_accepted_at' => $createdAt,
                'created_at' => $createdAt,
            ]);
        }

        if ($overwritePasswords) {
            $values['password'] = $row->password;
        }

        DB::table('users')->where('id', $existing->id)->update($values);

        return (int) $existing->id;
    }

    private function saveProfile(stdClass $row, int $userId): void
    {
        $values = [
            'phone' => $row->phone ?: null,
            'date_of_birth' => $row->dob ?: null,
            'address' => $row->address ?: null,
            'updated_at' => now(),
        ];

        if (DB::table('profiles')->where('user_id', $userId)->exists()) {
            DB::table('profiles')->where('user_id', $userId)->update($values);

            return;
        }

        DB::table('profiles')->insert($values + ['user_id' => $userId, 'created_at' => now()]);
    }
}
