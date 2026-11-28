<?php

namespace App\Content\Home;

class JoinSection extends HomeSection
{
    public function key(): string
    {
        return 'home.join';
    }

    public function label(): string
    {
        return 'Join Us banner';
    }

    public function hint(): string
    {
        return 'The crimson band before About Us, in very large type, above the Join Us button.';
    }

    public function defaults(): array
    {
        return ['headline' => 'Join Our Community of Research Professionals to Stay Ahead'];
    }

    public function prepare(array $input): array
    {
        return ['headline' => self::text(str_replace("\n", ' ', (string) ($input['headline'] ?? '')))];
    }

    public function rules(): array
    {
        return ['headline' => ['required', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return [
            'headline.required' => 'Write the headline.',
            'headline.max' => 'Keep the headline under 100 characters; it is set very large.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['headline' => 'Headline'];
    }
}
