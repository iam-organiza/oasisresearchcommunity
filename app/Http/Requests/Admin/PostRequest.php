<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // An empty slug is built from the title, as the first site did.
        $slug = Str::slug((string) ($this->input('slug') ?: $this->input('title')));

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Post::class)->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'author_name' => ['required', 'string', 'max:255'],
            'post_category_id' => ['required', 'integer', Rule::exists('post_categories', 'id')],
            'tags' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'visibility' => ['required', Rule::enum(PostVisibility::class)],
            'published_at' => ['nullable', 'date', Rule::requiredIf($this->input('status') === PostStatus::Scheduled->value)],
            'reading_time' => ['nullable', 'integer', 'min:0', 'max:600'],
            'content_type' => ['required', Rule::in(['text', 'video'])],
            'allow_comments' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_editors_choice' => ['boolean'],
            'is_popular' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'featured_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'featured_image_file.max' => 'The image must be 5 MB or smaller.',
            'published_at.required' => 'Choose the date and time a scheduled post should go out.',
            'post_category_id.required' => 'Choose a category.',
        ];
    }
}
