<?php

namespace App\Content;

use App\Support\ArticleHtml;
use Carbon\CarbonImmutable;

/**
 * A legal page's text (Terms & Conditions, Privacy Policy): admins only,
 * edited as HTML so its layout classes survive, and cleaned like articles.
 * The support email, the office address and the site's own address are kept
 * as placeholders, so they follow Contact & social and each environment.
 */
class LegalSection extends Section
{
    /**
     * @param  view-string  $view  The built-in text, shown until the page is edited.
     */
    public function __construct(
        private string $key,
        private string $name,
        private string $view,
        private ?string $defaultUpdated,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function page(): string
    {
        return 'legal';
    }

    public function label(): string
    {
        return $this->name;
    }

    public function hint(): string
    {
        return 'Legal text: change it only with approval. Headings marked h3 become the numbered contents list.';
    }

    public function adminOnly(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        return [
            // Cleaned as a saved version would be, so putting the original back counts as the original.
            'body' => self::clean(view($this->view)->render()),
            'updated' => $this->defaultUpdated,
        ];
    }

    /**
     * Cleaned like an article; the cleaner writes "@" as "&#64;", which HTML
     * does not need, so it is put back to keep the text readable when edited.
     */
    private static function clean(string $html): string
    {
        return trim(str_replace('&#64;', '@', ArticleHtml::clean($html)));
    }

    /**
     * @return array<string, string>
     */
    private static function placeholders(): array
    {
        $site = url('/');

        // Longest first, so the site's address is replaced before its host name inside it.
        return [
            '{{site_url}}' => $site,
            '{{site_host}}' => (string) parse_url($site, PHP_URL_HOST),
            '{{support_email}}' => (string) config('oasis.support_email'),
            '{{address}}' => implode(' ', array_filter((array) config('oasis.address'))),
        ];
    }

    public function prepare(array $input): array
    {
        $body = self::clean((string) ($input['body'] ?? ''));

        $updated = ($input['touch_updated'] ?? false) === true || ($input['touch_updated'] ?? '') === '1'
            ? CarbonImmutable::now()->format('j F Y')
            : (($input['updated'] ?? null) ?: null);

        return ['body' => trim($body), 'updated' => $updated];
    }

    public function toStorage(array $value): array
    {
        $body = (string) ($value['body'] ?? '');

        foreach (self::placeholders() as $token => $real) {
            if ($real !== '') {
                $body = str_replace($real, $token, $body);
            }
        }

        $value['body'] = $body;

        return $value;
    }

    public function fromStorage(array $value): array
    {
        $value['body'] = strtr((string) ($value['body'] ?? ''), self::placeholders());

        return $value;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:100000'],
            'updated' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return ['body.required' => 'The page cannot be empty.'];
    }

    public function fieldLabels(): array
    {
        return ['body' => 'Text', 'updated' => 'Last updated'];
    }

    public function describe(string $field, mixed $value): string
    {
        if ($field === 'body') {
            return str_word_count(strip_tags((string) $value)).' words';
        }

        return parent::describe($field, $value);
    }
}
