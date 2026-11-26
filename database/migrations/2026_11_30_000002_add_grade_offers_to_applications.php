<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The site offers a grade as soon as an application is submitted (OASIS,
 * 2026-11-27): it ranks the answers against the grade criteria, and the
 * Registrar oversees rather than reviewing every application first. No
 * referee is asked for when applying.
 *
 * - role_type, currently_studying: structured answers the ranking needs.
 * - other_evidence: anything else relevant, for the Registrar to read.
 * - offered_grade, qualifying_grade, offer_reasons, offered_at: the offer.
 * - flags: why the Registrar should look (Fellow offer, other evidence,
 *   exemption, ...); reviewed_at once they have.
 * - accepted_at: the applicant accepted the offer.
 * - "OND, HND or similar" splits into OND and HND, since an HND counts as a
 *   first degree for the grades and an OND does not.
 * - The Registrar's "recommended" decisions become offers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('role_type', 30)->nullable()->after('current_role');
            $table->string('currently_studying', 20)->nullable()->after('role_type');
            $table->text('other_evidence')->nullable()->after('claimed_areas');
            $table->string('offered_grade', 20)->nullable()->after('decided_grade');
            $table->string('qualifying_grade', 20)->nullable()->after('offered_grade');
            $table->json('offer_reasons')->nullable()->after('qualifying_grade');
            $table->json('flags')->nullable()->after('offer_reasons');
            $table->timestamp('offered_at')->nullable()->after('flags');
            $table->timestamp('accepted_at')->nullable()->after('offered_at');
            $table->timestamp('reviewed_at')->nullable()->after('accepted_at');
        });

        DB::table('membership_applications')->where('highest_qualification', 'diploma')->update(['highest_qualification' => 'ond']);
        DB::table('membership_applications')->where('status', 'recommended')->update([
            'status' => 'offered',
            'offered_grade' => DB::raw('decided_grade'),
            'offered_at' => DB::raw('decided_at'),
        ]);
    }

    public function down(): void
    {
        DB::table('membership_applications')->where('status', 'offered')->whereNotNull('decided_grade')->update(['status' => 'recommended']);
        DB::table('membership_applications')->whereIn('status', ['offered', 'accepted'])->update(['status' => 'submitted']);
        DB::table('membership_applications')->whereIn('highest_qualification', ['ond', 'hnd'])->update(['highest_qualification' => 'diploma']);

        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn([
                'role_type', 'currently_studying', 'other_evidence', 'offered_grade', 'qualifying_grade',
                'offer_reasons', 'flags', 'offered_at', 'accepted_at', 'reviewed_at',
            ]);
        });
    }
};
