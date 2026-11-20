<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // A staff account for working on the site locally. Never created
        // on the live site.
        if (app()->isProduction()) {
            return;
        }

        $staff = User::firstOrNew(['email' => 'staff@example.test']);
        $staff->forceFill([
            'first_name' => 'Local',
            'last_name' => 'Staff',
            'password' => 'password',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();
    }
}
