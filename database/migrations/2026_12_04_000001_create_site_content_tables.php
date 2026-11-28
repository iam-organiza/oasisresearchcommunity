<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public site's editable content. Each section (contact details, and
 * later the home page's sections and the legal pages) is one row holding
 * its fields as JSON; a section with no row shows the built-in text. Every
 * save, and every restore, is kept as a revision with who made it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('site_content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->index();
            // The whole section as it stood after this change.
            $table->json('value');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // saved, or restored (from an earlier revision or the original text).
            $table->string('action', 20)->default('saved');
            $table->unsignedBigInteger('restored_from_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_content_revisions');
        Schema::dropIfExists('site_contents');
    }
};
