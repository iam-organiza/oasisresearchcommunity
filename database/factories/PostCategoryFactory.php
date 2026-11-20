<?php

namespace Database\Factories;

use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostCategory>
 */
class PostCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = implode(' ', (array) fake()->unique()->words(2));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
