<?php

namespace Database\Factories;

use App\Enums\CommentStatus;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostComment>
 */
class PostCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'content' => fake()->sentence(12),
            'status' => CommentStatus::Approved,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => CommentStatus::Pending]);
    }
}
