<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After the exam (OASIS, 2026-11-27):
 * - the membership number is issued when the induction fee is settled and
 *   the account goes live, so it can be empty until then;
 * - a new member chooses the induction ceremony or the pack posted to
 *   them; the delivery address is kept only until it is delivered;
 * - a charge can carry a short note ("less the exam fee");
 * - postage for a posted pack is a fee on the Fees screen, ₦0 until set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('number', 40)->nullable()->change();
            $table->string('induction_delivery', 20)->nullable()->after('lapsed_at');
            $table->string('delivery_address', 300)->nullable()->after('induction_delivery');
            $table->string('delivery_city', 100)->nullable()->after('delivery_address');
            $table->foreignId('delivery_state_id')->nullable()->after('delivery_city')->constrained('states')->nullOnDelete();
            $table->string('delivery_phone', 20)->nullable()->after('delivery_state_id');
            $table->timestamp('pack_posted_at')->nullable()->after('delivery_phone');
            $table->timestamp('pack_delivered_at')->nullable()->after('pack_posted_at');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->string('note', 200)->nullable()->after('amount');
        });

        DB::table('price_settings')->insert(['key' => 'postage', 'amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('price_settings')->where('key', 'postage')->delete();

        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_state_id');
            $table->dropColumn(['induction_delivery', 'delivery_address', 'delivery_city', 'delivery_phone', 'pack_posted_at', 'pack_delivered_at']);
        });
    }
};
