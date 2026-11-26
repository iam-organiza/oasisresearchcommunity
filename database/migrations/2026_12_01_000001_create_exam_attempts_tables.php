<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The membership exam (OASIS, 2026-11-26/27): 50 multiple-choice questions
 * in 60 minutes, 60% to pass, three attempts per exam fee.
 *
 * Each attempt keeps its own copy of the questions as they were set (text,
 * options in the order shown, which one is right), so editing or retiring
 * a question later never changes a past paper or its mark.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('grade', 20);
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('correct')->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->json('area_scores')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'finished_at']);
        });

        Schema::create('exam_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_question_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('area', 40);
            $table->text('scenario')->nullable();
            $table->text('question');
            $table->json('options');
            $table->unsignedTinyInteger('correct_option');
            $table->unsignedTinyInteger('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->unique(['exam_attempt_id', 'position']);
        });

        Schema::table('membership_applications', function (Blueprint $table) {
            $table->decimal('exam_best_percent', 5, 2)->nullable()->after('exam_passed_on');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn('exam_best_percent');
        });
        Schema::dropIfExists('exam_attempt_questions');
        Schema::dropIfExists('exam_attempts');
    }
};
