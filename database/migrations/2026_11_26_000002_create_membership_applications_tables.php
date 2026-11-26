<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A membership application, saved step by step. The answers sit on the
 * application itself (not the user's profile) so the registrar sees exactly
 * what was submitted, even if the person's profile changes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->json('completed_steps')->nullable();

            // About you
            $table->string('title', 36)->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 100)->nullable();

            // Grade
            $table->string('grade', 20)->nullable();
            $table->string('fellow_basis', 20)->nullable();
            $table->text('grade_evidence')->nullable();

            // Experience
            $table->string('highest_qualification', 30)->nullable();
            $table->string('current_role', 150)->nullable();
            $table->string('institution', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->json('competencies')->nullable();

            // Route and induction
            $table->string('route', 20)->nullable();
            $table->string('exam_status', 20)->nullable();
            $table->date('exam_passed_on')->nullable();
            $table->string('induction_event', 20)->nullable();
            $table->string('induction_status', 20)->nullable();
            $table->date('induction_attended_on')->nullable();

            // Referee (optional for now, OASIS 2026-11-26)
            $table->string('referee_name', 150)->nullable();
            $table->string('referee_email')->nullable();
            $table->string('referee_relationship', 150)->nullable();
            $table->string('referee_membership_number', 40)->nullable();

            // Review
            $table->boolean('directory_consent')->default(false);
            $table->boolean('keep_documents_two_years')->default(false);
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });

        Schema::create('application_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')->constrained()->cascadeOnDelete();
            $table->string('title', 300);
            $table->string('venue', 200);
            $table->unsignedSmallInteger('year');
            $table->string('link', 500)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_publications');
        Schema::dropIfExists('membership_applications');
    }
};
