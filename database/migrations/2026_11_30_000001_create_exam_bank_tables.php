<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The membership exam's question bank and its prep materials, kept by the
 * Registrar (OASIS, 2026-11-26/27). Each question belongs to one research
 * area; whether it is basic or advanced follows the area. Questions are
 * retired rather than deleted once the exam has used them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->string('area', 40)->index();
            $table->text('scenario')->nullable();
            $table->text('question');
            $table->json('options');
            $table->unsignedTinyInteger('correct_option');
            $table->text('explanation')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prep_materials', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('area', 40)->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->boolean('published')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prep_materials');
        Schema::dropIfExists('exam_questions');
    }
};
