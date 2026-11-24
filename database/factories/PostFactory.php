<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(18),
            'content' => '<p>'.implode('</p><p>', (array) fake()->paragraphs(3)).'</p>',
            'reading_time' => 4,
            'status' => PostStatus::Published,
            'visibility' => PostVisibility::Public,
            'author_name' => 'Editor',
            'post_category_id' => PostCategory::factory(),
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PostStatus::Draft, 'published_at' => null]);
    }
}
