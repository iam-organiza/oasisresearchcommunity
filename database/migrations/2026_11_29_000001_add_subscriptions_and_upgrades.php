<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yearly subscriptions, lapsing and upgrades (OASIS, 2026-11-27).
 *
 * - A subscription charge has the date it is due; the reminders sent for it
 *   are noted so none goes twice.
 * - A member lapses 30 days after an unpaid subscription falls due
 *   (memberships.lapsed_at), and is active again once it is paid.
 * - grade_since: when the member reached their grade, for upgrade prices.
 * - An upgrade is a charge for the new grade; once it is settled the grade
 *   changes (charges.applied_at).
 * - Members can stop the monthly newsletter (users.newsletter_opted_out_at).
 * - Fees: the upgrade admin charge becomes 10% (was 20%); discounts for
 *   upgrading after one and two years, and for a promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->date('due_on')->nullable()->after('year');
            $table->timestamp('reminded_due_at')->nullable()->after('cancelled_by');
            $table->timestamp('reminded_final_at')->nullable()->after('reminded_due_at');
            $table->timestamp('applied_at')->nullable()->after('reminded_final_at');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->date('grade_since')->nullable()->after('grade');
            $table->timestamp('lapsed_at')->nullable()->after('activated_at');
        });
        DB::table('memberships')->update(['grade_since' => DB::raw('joined_on')]);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('newsletter_opted_out_at')->nullable();
        });

        $now = now();
        DB::table('price_settings')->insert([
            ['key' => 'upgrade_discount_1y', 'amount' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'upgrade_discount_2y', 'amount' => 15, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'promotion_discount', 'amount' => 20, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // OASIS changed the upgrade admin charge from 20% to 10%. Only if nobody has set it by hand since.
        $current = DB::table('price_settings')->where('key', 'upgrade_percent')->value('amount');
        if ((int) $current === 20) {
            DB::table('price_settings')->where('key', 'upgrade_percent')->update(['amount' => 10, 'updated_at' => $now]);
            DB::table('price_setting_changes')->insert([
                'key' => 'upgrade_percent', 'old_amount' => 20, 'new_amount' => 10,
                'reason' => 'OASIS decision, 27 November 2026: 10% admin fee on an upgrade within the first year.',
                'user_id' => null, 'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('price_settings')->whereIn('key', ['upgrade_discount_1y', 'upgrade_discount_2y', 'promotion_discount'])->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('newsletter_opted_out_at');
        });
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['grade_since', 'lapsed_at']);
        });
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['due_on', 'reminded_due_at', 'reminded_final_at', 'applied_at']);
        });
    }
};
