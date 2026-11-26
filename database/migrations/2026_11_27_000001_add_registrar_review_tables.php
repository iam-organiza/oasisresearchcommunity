<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The registrar's review (OASIS, 2026-11-26):
 * - applicants tick the research areas they claim experience in (the exam
 *   will draw on them) instead of rating five competencies;
 * - "direct election" becomes a request to be exempted from the exam, with
 *   a reason and links to public evidence, decided by the registrar;
 * - each decision keeps its reason and what to improve, and every step is
 *   logged in application_events;
 * - approved applicants get a membership record with a number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->json('claimed_areas')->nullable()->after('years_experience');
            $table->text('exemption_reason')->nullable()->after('route');
            $table->json('exemption_links')->nullable()->after('exemption_reason');
            $table->string('exemption_status', 20)->nullable()->after('exemption_links');

            $table->string('decided_grade', 20)->nullable()->after('submitted_at');
            $table->text('decision_reason')->nullable()->after('decided_grade');
            $table->text('decision_improvements')->nullable()->after('decision_reason');
            $table->text('registrar_message')->nullable()->after('decision_improvements');
            $table->foreignId('decided_by')->nullable()->after('registrar_message')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->text('appeal_reason')->nullable()->after('decided_at');
            $table->boolean('fee_waived')->default(false)->after('appeal_reason');
        });

        // Nothing has been submitted anywhere yet; any draft that chose direct election now asks for an exemption.
        DB::table('membership_applications')->where('route', 'direct')->update(['route' => 'exemption']);

        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn('competencies');
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('grade', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('membership_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('grade', 20)->index();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->date('joined_on');
            $table->timestamp('induction_paid_at')->nullable();
            $table->boolean('fee_waived')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('application_events');

        Schema::table('membership_applications', function (Blueprint $table) {
            $table->json('competencies')->nullable();
        });

        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn([
                'claimed_areas', 'exemption_reason', 'exemption_links', 'exemption_status',
                'decided_grade', 'decision_reason', 'decision_improvements', 'registrar_message',
                'decided_at', 'appeal_reason', 'fee_waived',
            ]);
        });
    }
};
