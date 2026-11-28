<?php

namespace App\Content\Home;

use App\Content\Section;

/**
 * A section of the home page: editors and admins may change it, and it is
 * edited on the Home page screen with the others.
 */
abstract class HomeSection extends Section
{
    public function page(): string
    {
        return 'home';
    }

    public function adminOnly(): bool
    {
        return false;
    }

    protected static function text(mixed $value): string
    {
        // One space for runs of spaces; line breaks kept, at most one blank line between paragraphs.
        $text = preg_replace(['/[ \\t]+/', '/\\n{3,}/'], [' ', "\n\n"], str_replace("\r\n", "\n", trim((string) $value)));

        return (string) $text;
    }

    /**
     * Title-and-text items (services, benefits), with blank rows dropped.
     *
     * @return list<array{title: string, text: string}>
     */
    protected static function items(mixed $list, bool $keepBlank = false): array
    {
        $items = array_map(fn ($item) => [
            'title' => self::text(is_array($item) ? ($item['title'] ?? '') : ''),
            'text' => self::text(is_array($item) ? ($item['text'] ?? '') : ''),
        ], is_array($list) ? array_values($list) : []);

        return $keepBlank ? $items : array_values(array_filter($items, fn (array $item) => $item['title'] !== '' || $item['text'] !== ''));
    }

    public function describe(string $field, mixed $value): string
    {
        // A list of items reads as its titles, with the text of each.
        if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            return implode('; ', array_map(fn (array $item) => trim(($item['title'] ?? '').': '.($item['text'] ?? ''), ': '), $value));
        }

        return parent::describe($field, $value);
    }
}
