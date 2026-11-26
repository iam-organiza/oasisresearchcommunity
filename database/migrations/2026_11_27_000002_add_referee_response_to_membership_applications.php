<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named referee is emailed a link to say whether they can vouch for the
 * applicant; their answer is kept here for the registrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('referee_status', 20)->nullable()->after('referee_membership_number');
            $table->timestamp('referee_responded_at')->nullable()->after('referee_status');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn(['referee_status', 'referee_responded_at']);
        });
    }
};
