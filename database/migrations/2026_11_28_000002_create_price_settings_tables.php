<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fees move from config/membership.php into a table staff can edit, with a
 * history of every change. The config keeps the starting figures. Also the
 * daily dollar rate, used to show what a naira price is in dollars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->unsignedInteger('amount')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('price_setting_changes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->index();
            $table->unsignedInteger('old_amount')->nullable();
            $table->unsignedInteger('new_amount')->nullable();
            $table->string('reason', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('naira_per_dollar', 12, 4);
            $table->string('source', 40);
            $table->timestamp('fetched_at')->index();
        });

        $now = now();
        $rows = [
            ['key' => 'exam', 'amount' => (int) config('membership.exam_fee')],
            ['key' => 'upgrade_percent', 'amount' => (int) config('membership.upgrade_percent')],
            ['key' => 'usd_rate_override', 'amount' => null],
        ];
        foreach (config('membership.grades') as $grade => $fees) {
            $rows[] = ['key' => "induction.{$grade}", 'amount' => (int) $fees['induction_fee']];
            $rows[] = ['key' => "subscription.{$grade}", 'amount' => (int) $fees['annual_dues']];
        }
        DB::table('price_settings')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('price_setting_changes');
        Schema::dropIfExists('price_settings');
    }
};
