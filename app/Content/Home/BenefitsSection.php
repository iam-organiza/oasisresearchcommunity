<?php

namespace App\Content\Home;

class BenefitsSection extends HomeSection
{
    public function key(): string
    {
        return 'home.benefits';
    }

    public function label(): string
    {
        return 'Membership Benefits';
    }

    public function hint(): string
    {
        return 'The navy half of the Membership section. One to five benefits.';
    }

    public function defaults(): array
    {
        return [
            'intro' => 'We operate membership system that delivers valuable benefits for members including subsidized access to our resources, networking, sharing of information on valuable opportunities, and paid consultancies, among other benefits.',
            'benefits' => [
                ['title' => 'Information', 'text' => 'Frequent updates on research opportunities in academics and other industries, e.g. grants'],
                ['title' => 'Payment', 'text' => 'Paid participation on research consulting projects for our corporate clients and public sector.'],
                ['title' => 'Services', 'text' => 'Up to 100% waivers on publishing and author visibility services for subscribed members.'],
            ],
        ];
    }

    public function prepare(array $input): array
    {
        return [
            'intro' => self::text($input['intro'] ?? ''),
            'benefits' => self::items($input['benefits'] ?? []),
        ];
    }

    public function rules(): array
    {
        return [
            'intro' => ['required', 'string', 'max:400'],
            'benefits' => ['array', 'min:1', 'max:5'],
            'benefits.*.title' => ['required', 'string', 'max:24'],
            'benefits.*.text' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'intro.required' => 'Write the introduction.',
            'intro.max' => 'Keep the introduction under 400 characters.',
            'benefits.min' => 'List at least one benefit.',
            'benefits.max' => 'List up to five benefits.',
            'benefits.*.title.required' => 'Give the benefit a name.',
            'benefits.*.title.max' => 'Keep the name under 24 characters.',
            'benefits.*.text.required' => 'Say what the benefit is.',
            'benefits.*.text.max' => 'Keep it under 200 characters.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['intro' => 'Introduction', 'benefits' => 'Benefits'];
    }
}
