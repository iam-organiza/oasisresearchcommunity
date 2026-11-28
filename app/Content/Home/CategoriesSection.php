<?php

namespace App\Content\Home;

class CategoriesSection extends HomeSection
{
    public function key(): string
    {
        return 'home.categories';
    }

    public function label(): string
    {
        return 'Membership Categories';
    }

    public function hint(): string
    {
        return 'The white half of the Membership section. The category names (Students to Fellows) follow the membership grades, so they are not edited here.';
    }

    public function defaults(): array
    {
        return [
            'intro' => 'In line with its mandate to optimize the practice and outcomes of academic and industry research, the organization registers eligible persons who meet requirements for registration into any of the four (4) individual membership categories for which the organization maintains a register, and admits organizations as institutional members.',
            'institutional' => 'Universities, research institutes, companies and other organizations.',
        ];
    }

    public function prepare(array $input): array
    {
        return [
            'intro' => self::text($input['intro'] ?? ''),
            'institutional' => self::text($input['institutional'] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [
            'intro' => ['required', 'string', 'max:500'],
            'institutional' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'intro.required' => 'Write the introduction.',
            'intro.max' => 'Keep the introduction under 500 characters.',
            'institutional.required' => 'Say who can be an institutional member.',
            'institutional.max' => 'Keep it under 200 characters.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['intro' => 'Introduction', 'institutional' => 'Institutional Members line'];
    }
}
