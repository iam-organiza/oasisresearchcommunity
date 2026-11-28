<?php

namespace App\Content\Home;

class HeroSection extends HomeSection
{
    public function key(): string
    {
        return 'home.hero';
    }

    public function label(): string
    {
        return 'Opening';
    }

    public function hint(): string
    {
        return 'The first thing on the page, under the name and tagline. The tagline itself is under Contact & social.';
    }

    public function defaults(): array
    {
        return [
            'intro' => 'We believe that development at local, national or global levels are all greatly influenced by quality solution-driven research in government, academia and Industries',
        ];
    }

    public function prepare(array $input): array
    {
        return ['intro' => self::text($input['intro'] ?? '')];
    }

    public function rules(): array
    {
        return ['intro' => ['required', 'string', 'max:400']];
    }

    public function messages(): array
    {
        return [
            'intro.required' => 'Write the opening paragraph.',
            'intro.max' => 'Keep the opening under 400 characters, so it sits beside the logo.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['intro' => 'Opening paragraph'];
    }
}
