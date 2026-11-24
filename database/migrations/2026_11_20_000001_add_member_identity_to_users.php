<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Public identifier carried over from the first site, so links and
            // records that point at a member keep working after the move.
            $table->uuid('user_ref')->nullable()->unique()->after('id');
            $table->string('first_name', 100)->default('')->after('user_ref');
            $table->string('last_name', 100)->default('')->after('first_name');
            $table->string('role', 20)->default('member')->index()->after('password');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->default('')->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['user_ref']);
            $table->dropIndex(['role']);
            $table->dropColumn(['user_ref', 'first_name', 'last_name', 'role']);
        });
    }
};
