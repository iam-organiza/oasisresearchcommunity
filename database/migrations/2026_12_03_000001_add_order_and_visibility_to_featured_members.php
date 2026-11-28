<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Featured members get an order chosen on the Website screen (until now
 * the home page showed them in the order they were added) and can be
 * hidden without being deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('featured_members', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('member_ref');
            $table->boolean('is_visible')->default(true)->after('sort_order');
        });

        // Keep today's order: the order they were added in.
        foreach (DB::table('featured_members')->orderBy('id')->pluck('id') as $i => $id) {
            DB::table('featured_members')->where('id', $id)->update(['sort_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('featured_members', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'is_visible']);
        });
    }
};
