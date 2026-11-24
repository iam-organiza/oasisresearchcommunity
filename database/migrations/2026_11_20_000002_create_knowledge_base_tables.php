<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            // Reference carried over from the first site.
            $table->string('post_ref', 64)->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->string('canonical_url')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->unsignedInteger('reading_time')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->string('visibility', 20)->default('public');
            // The first site stored the byline as free text; author_id is set
            // when a post is written by a signed-in member of staff.
            $table->string('author_name');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('post_category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('allow_comments')->default(true);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_editors_choice')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->string('content_type', 20)->default('text');
            $table->dateTime('published_at')->nullable()->index();
            $table->string('featured_image')->nullable();
            $table->string('og_image')->nullable();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            // Set only on rows copied from the first site, so a second import
            // updates them instead of adding them again.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('post_comments')->cascadeOnDelete();
            $table->string('author_name');
            $table->string('author_email');
            $table->text('content');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('featured_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('member_ref')->unique();
            $table->string('avatar')->nullable();
            $table->string('title', 36)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('position', 100)->nullable();
            $table->string('speciality', 100)->nullable();
            $table->text('job_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_members');
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('post_categories');
    }
};
