<?php

namespace App\Content\Home;

class ServicesSection extends HomeSection
{
    public function key(): string
    {
        return 'home.services';
    }

    public function label(): string
    {
        return 'What We Do';
    }

    public function hint(): string
    {
        return 'The four services, each on its own coloured page. There are always four, one for each colour.';
    }

    public function defaults(): array
    {
        return [
            'intro' => 'We collaborate with our partner organizations, institutions and industry professionals to provide services essential to optimizing research quality and impact for professionals and enthusiasts',
            'services' => [
                ['title' => 'Training', 'text' => 'We offer trainings and certifications on contemporary research methods and practices'],
                ['title' => 'Publishing', 'text' => 'We offer high quality open-access peer-reviewed research publishing along with digitization, branding and digital marketing services for researchers'],
                ['title' => 'Consulting', 'text' => 'We carry out as NEEDS, assessments, MEL, and other research projects on behalf of companies, governments and local/international NGOs'],
                ['title' => 'Membership', 'text' => 'We induct and maintain a database of highly competent researchers as members of the OASIS Research Community'],
            ],
        ];
    }

    public function prepare(array $input): array
    {
        return [
            'intro' => self::text($input['intro'] ?? ''),
            'services' => array_slice(array_pad(self::items($input['services'] ?? [], keepBlank: true), 4, ['title' => '', 'text' => '']), 0, 4),
        ];
    }

    public function rules(): array
    {
        return [
            'intro' => ['required', 'string', 'max:400'],
            'services' => ['array', 'size:4'],
            'services.*.title' => ['required', 'string', 'max:24'],
            'services.*.text' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'intro.required' => 'Write the introduction.',
            'intro.max' => 'Keep the introduction under 400 characters.',
            'services.*.title.required' => 'Give the service a name.',
            'services.*.title.max' => 'Keep the name under 24 characters, so it fits the page in large type.',
            'services.*.text.required' => 'Say what the service is.',
            'services.*.text.max' => 'Keep it under 200 characters, so the four pages stay even.',
        ];
    }

    public function fieldLabels(): array
    {
        return ['intro' => 'Introduction', 'services' => 'Services'];
    }
}
