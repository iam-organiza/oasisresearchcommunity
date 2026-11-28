<?php

namespace App\Content;

/**
 * What search engines (and links shared on social media) show for one
 * public page: its title and the short description under it. Edited on that
 * page's own screen; editors and admins may change it.
 */
class SearchSection extends Section
{
    public function __construct(
        private string $key,
        private string $page,
        private string $pageName,
        private string $defaultTitle,
        private string $defaultDescription,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function page(): string
    {
        return $this->page;
    }

    public function label(): string
    {
        return "{$this->pageName} in search results";
    }

    public function hint(): string
    {
        return 'What Google and other search engines show for this page, and what appears when a link to it is shared.';
    }

    public function adminOnly(): bool
    {
        return false;
    }

    public function defaults(): array
    {
        return ['title' => $this->defaultTitle, 'description' => $this->defaultDescription];
    }

    public function prepare(array $input): array
    {
        $clean = fn (mixed $value): string => trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return ['title' => $clean($input['title'] ?? ''), 'description' => $clean($input['description'] ?? '')];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Write the title search engines show.',
            'title.max' => 'Keep the title under 60 characters; search engines cut longer ones short.',
            'description.required' => 'Write the description search engines show.',
            'description.max' => 'Keep the description under 160 characters; search engines cut longer ones short.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['title' => 'Search title', 'description' => 'Search description'];
    }
}
