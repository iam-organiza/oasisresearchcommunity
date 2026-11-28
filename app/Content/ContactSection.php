<?php

namespace App\Content;

use Closure;
use Illuminate\Support\Str;

/**
 * The organisation's contact details and social links: the header, footer,
 * Contact Us section, emails, receipts and the privacy policy all show them.
 * The built-in values are config/oasis.php; saved values replace them.
 */
class ContactSection extends Section
{
    /** The platforms with an icon on the site; a link left empty hides its icon. */
    public const PLATFORMS = [
        'Facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'Instagram' => ['instagram.com'],
        'LinkedIn' => ['linkedin.com'],
        'WhatsApp' => ['whatsapp.com', 'wa.me'],
    ];

    public function key(): string
    {
        return 'contact';
    }

    public function label(): string
    {
        return 'Contact & social';
    }

    public function adminOnly(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        $file = config('oasis._defaults') ?? config('oasis');

        return [
            'tagline' => (string) $file['tagline'],
            'support_email' => (string) $file['support_email'],
            'phones' => array_values($file['phones']),
            'address' => array_values($file['address']),
            'map_embed_url' => (string) $file['map_embed_url'],
            'social' => collect(array_keys(self::PLATFORMS))
                ->mapWithKeys(fn (string $name) => [$name => (string) ($file['social'][$name] ?? '')])
                ->all(),
        ];
    }

    public function prepare(array $input): array
    {
        $map = trim((string) ($input['map_embed_url'] ?? ''));

        // People copy the whole <iframe> from Google Maps' "Embed a map"; keep only its address.
        if (preg_match('/src=["\']([^"\']+)["\']/i', $map, $match)) {
            $map = html_entity_decode($match[1]);
        }

        $lines = fn (mixed $list): array => array_values(array_filter(
            array_map(fn ($line) => trim((string) $line), is_array($list) ? $list : []),
            fn (string $line) => $line !== '',
        ));

        return [
            'tagline' => trim((string) ($input['tagline'] ?? '')),
            'support_email' => Str::lower(trim((string) ($input['support_email'] ?? ''))),
            'phones' => $lines($input['phones'] ?? []),
            'address' => array_map(fn ($line) => trim((string) $line), array_pad(array_slice((array) ($input['address'] ?? []), 0, 3), 3, '')),
            'map_embed_url' => $map,
            'social' => collect(array_keys(self::PLATFORMS))
                ->mapWithKeys(fn (string $name) => [$name => trim((string) ($input['social'][$name] ?? ''))])
                ->all(),
        ];
    }

    public function fromStorage(array $value): array
    {
        // The icons keep their fixed order: Facebook, Instagram, LinkedIn, WhatsApp.
        if (isset($value['social']) && is_array($value['social'])) {
            $social = $value['social'];
            $value['social'] = collect(array_keys(self::PLATFORMS))
                ->mapWithKeys(fn (string $name) => [$name => (string) ($social[$name] ?? '')])
                ->all();
        }

        return $value;
    }

    public function rules(): array
    {
        $social = collect(self::PLATFORMS)->mapWithKeys(fn (array $hosts, string $name) => [
            "social.{$name}" => ['nullable', 'url:https', 'max:500', function (string $attribute, mixed $value, Closure $fail) use ($hosts, $name): void {
                $host = Str::lower((string) parse_url((string) $value, PHP_URL_HOST));

                if (! collect($hosts)->contains(fn (string $allowed) => $host === $allowed || Str::endsWith($host, '.'.$allowed))) {
                    $fail("That is not a {$name} link. It should start https://".$hosts[0].'/');
                }
            }],
        ])->all();

        return [
            'tagline' => ['required', 'string', 'max:60'],
            'support_email' => ['required', 'email', 'max:255'],
            'phones' => ['array', 'min:1', 'max:3'],
            'phones.*' => ['string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,}$/'],
            'address' => ['array', 'size:3'],
            'address.0' => ['required', 'string', 'max:80'],
            'address.1' => ['required', 'string', 'max:120'],
            'address.2' => ['required', 'string', 'max:120'],
            'map_embed_url' => ['required', 'url:https', 'max:2000', function (string $attribute, mixed $value, Closure $fail): void {
                $parts = parse_url((string) $value);

                if (($parts['host'] ?? '') !== 'www.google.com' || ! Str::startsWith($parts['path'] ?? '', '/maps/embed')) {
                    $fail('Paste the link from Google Maps: Share, then Embed a map, then Copy HTML.');
                }
            }],
            ...$social,
        ];
    }

    public function messages(): array
    {
        return [
            'tagline.required' => 'Enter the tagline.',
            'tagline.max' => 'Keep the tagline under 60 characters, so it fits beside the logo.',
            'support_email.required' => 'Enter the email address people should write to.',
            'support_email.email' => 'Enter an email address like name@example.com.',
            'phones.min' => 'Give at least one phone number.',
            'phones.max' => 'Give up to three phone numbers.',
            'phones.*.regex' => 'Use digits, spaces and a leading + only, like +234 703 635 6635.',
            'address.0.required' => 'Enter the office name, such as National Secretariat.',
            'address.1.required' => 'Enter the street address.',
            'address.2.required' => 'Enter the town and country.',
            'map_embed_url.required' => 'Paste the Google Maps embed link.',
            'map_embed_url.url' => 'Paste the link from Google Maps: Share, then Embed a map, then Copy HTML.',
            'social.*.url' => 'Enter the full link, starting https://',
        ];
    }

    public function fieldLabels(): array
    {
        return [
            'tagline' => 'Tagline',
            'support_email' => 'Email address',
            'phones' => 'Phone numbers',
            'address' => 'Address',
            'map_embed_url' => 'Map',
            'social' => 'Social links',
        ];
    }

    public function describe(string $field, mixed $value): string
    {
        if ($field === 'map_embed_url') {
            return 'Map location changed';
        }

        if ($field === 'social' && is_array($value)) {
            return collect($value)->map(fn ($url, $name) => $url ? $name : null)->filter()->implode(', ') ?: '(none)';
        }

        return parent::describe($field, $value);
    }
}
