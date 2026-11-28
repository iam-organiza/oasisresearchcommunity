<?php

namespace App\Content\Home;

class AboutSection extends HomeSection
{
    public function key(): string
    {
        return 'home.about';
    }

    public function label(): string
    {
        return 'About Us';
    }

    public function hint(): string
    {
        return 'The introduction, the core values set very large, the vision and mission, and the registration line at the foot.';
    }

    public function defaults(): array
    {
        return [
            'intro' => 'OASIS Research Community was established to advance the practice of high-quality research by providing research training and support to research enthusiasts and professionals around the world, particularly from developing countries like Nigeria. OASIS creates and operates platforms that promote and optimize intellectual insights, research capabilities and research outcomes for our members and corporate clients.',
            'values' => ['Excellence', 'Ethics', 'Integrity'],
            'vision' => 'To sustainably optimize academic and professional research outcomes for stakeholders',
            'mission' => 'To entrench global research best practices in the academic and professional research community through research education, support, publishing, and marketing',
            'recognition' => 'OASIS Research Community is registered in Nigeria with the Corporate Affairs Commission (CAC) as a Company Limited by Guarantee with RC No:2008295 and authorization of the Attorney General of the Federation, the Federal Ministry of Education and the National Board of Technical Education (NBTE).',
        ];
    }

    public function prepare(array $input): array
    {
        $values = array_values(array_filter(
            // The page adds the full stop after each value.
            array_map(fn ($value) => rtrim(self::text($value), '.'), (array) ($input['values'] ?? [])),
            fn (string $value) => $value !== '',
        ));

        return [
            'intro' => self::text($input['intro'] ?? ''),
            'values' => $values,
            'vision' => self::text($input['vision'] ?? ''),
            'mission' => self::text($input['mission'] ?? ''),
            'recognition' => self::text($input['recognition'] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [
            'intro' => ['required', 'string', 'max:800'],
            'values' => ['array', 'min:1', 'max:4'],
            'values.*' => ['string', 'max:16'],
            'vision' => ['required', 'string', 'max:200'],
            'mission' => ['required', 'string', 'max:300'],
            'recognition' => ['required', 'string', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'intro.required' => 'Write the introduction.',
            'intro.max' => 'Keep the introduction under 800 characters.',
            'values.min' => 'Give at least one core value.',
            'values.max' => 'Give up to four core values; they are set very large across one line.',
            'values.*.max' => 'Keep each value to one word of up to 16 letters.',
            'vision.required' => 'Write the vision.',
            'vision.max' => 'Keep the vision under 200 characters.',
            'mission.required' => 'Write the mission.',
            'mission.max' => 'Keep the mission under 300 characters.',
            'recognition.required' => 'Write the registration line.',
            'recognition.max' => 'Keep the registration line under 400 characters.',
        ];
    }

    public function fieldLabels(): array
    {
        return [
            'intro' => 'Introduction',
            'values' => 'Core values',
            'vision' => 'Vision',
            'mission' => 'Mission',
            'recognition' => 'Registration line',
        ];
    }
}
