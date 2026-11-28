<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Validator;

class PostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // An empty slug is built from the title, as the first site did.
        $slug = Str::slug((string) ($this->input('slug') ?: $this->input('title')));

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string|RequiredIf>>
     */
    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');

        // A draft needs only a title, so half-written work can be kept; the rest is needed to go out.
        $goingOut = $this->input('status') !== PostStatus::Draft->value;

        return [
            'title' => ['required', 'string', 'max:255'],
            // Without a title there is no address to make, and the title's own message says enough.
            'slug' => [Rule::requiredIf($this->filled('title')), 'nullable', 'string', 'max:255', Rule::unique(Post::class)->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => [Rule::requiredIf($goingOut), 'nullable', 'string'],
            'author_name' => ['required', 'string', 'max:255'],
            'post_category_id' => [Rule::requiredIf($goingOut), 'nullable', 'integer', Rule::exists('post_categories', 'id')],
            'tags' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'visibility' => ['required', Rule::enum(PostVisibility::class)],
            'published_at' => ['nullable', 'date', Rule::requiredIf($this->input('status') === PostStatus::Scheduled->value)],
            'reading_time' => ['nullable', 'integer', 'min:0', 'max:600'],
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
            'remove_featured_image' => ['boolean'],
        ];
    }

    /**
     * The publish date has to agree with the status: "Published" goes out now,
     * "Scheduled" goes out later. A date that has not changed since the last
     * save is left alone, so an old post can still be edited.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $date = $this->date('published_at');

            if ($date === null || $validator->errors()->has('published_at')) {
                return;
            }

            /** @var Post|null $post */
            $post = $this->route('post');
            $unchanged = $post?->published_at !== null && $post->published_at->format('Y-m-d H:i') === $date->format('Y-m-d H:i');

            if ($unchanged) {
                return;
            }

            $status = $this->input('status');

            if ($status === PostStatus::Published->value && $date->isFuture()) {
                $validator->errors()->add('published_at', 'That date is still to come. To publish then, set the status to Scheduled; to publish now, clear the date.');
            }

            if ($status === PostStatus::Scheduled->value && ! $date->isFuture()) {
                $validator->errors()->add('published_at', 'A scheduled post needs a time still to come. To publish now, set the status to Published.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the post a title.',
            'slug.required' => 'Give the post an address with letters or numbers in it (under Search and sharing).',
            'slug.unique' => 'Another post already uses this address. Change the title, or the address under Search and sharing.',
            'content.required' => 'Write the article before it goes out. A draft can be saved without it.',
            'author_name.required' => 'Say who wrote it.',
            'post_category_id.required' => 'Choose a category before it goes out. A draft can be saved without one.',
            'featured_image_file.max' => 'The image must be 5 MB or smaller.',
            'published_at.required' => 'Choose the date and time a scheduled post should go out.',
        ];
    }
}
