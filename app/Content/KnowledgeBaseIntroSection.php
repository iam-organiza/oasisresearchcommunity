<?php

namespace App\Content;

/**
 * The line under the Knowledge Base heading.
 */
class KnowledgeBaseIntroSection extends Section
{
    public function key(): string
    {
        return 'knowledge-base.intro';
    }

    public function page(): string
    {
        return 'knowledge-base';
    }

    public function label(): string
    {
        return 'Introduction';
    }

    public function hint(): string
    {
        return 'Under the Knowledge Base heading, beside the search box.';
    }

    public function adminOnly(): bool
    {
        return false;
    }

    public function defaults(): array
    {
        return ['intro' => 'Research insights, methods and practical guides from the OASIS Research Community.'];
    }

    public function prepare(array $input): array
    {
        return ['intro' => trim((string) preg_replace('/\s+/', ' ', (string) ($input['intro'] ?? '')))];
    }

    public function rules(): array
    {
        return ['intro' => ['required', 'string', 'max:300']];
    }

    public function messages(): array
    {
        return [
            'intro.required' => 'Write the introduction.',
            'intro.max' => 'Keep the introduction under 300 characters.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['intro' => 'Introduction'];
    }
}
