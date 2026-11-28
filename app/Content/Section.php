<?php

namespace App\Content;

/**
 * One editable part of the public site: its fields, their built-in text,
 * how they are checked, and who may change them.
 */
abstract class Section
{
    /** Stored under this key; also names the section in the history. */
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * The editing page it appears on (admin/website/{page}); a page can hold
     * several sections, each saved on its own.
     */
    public function page(): string
    {
        return $this->key();
    }

    /** Where it is on the public site, for the editing page. */
    public function hint(): string
    {
        return '';
    }

    /** Only admins may change it (contact details, legal pages); otherwise editors may too. */
    abstract public function adminOnly(): bool;

    /**
     * Today's text, shown until someone saves the section.
     *
     * @return array<string, mixed>
     */
    abstract public function defaults(): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Each field's name as people know it, for the history.
     *
     * @return array<string, string>
     */
    abstract public function fieldLabels(): array;

    /**
     * A value as it is stored, the reverse of fromStorage(): every save and
     * restore passes through here.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public function toStorage(array $value): array
    {
        return $value;
    }

    /**
     * A stored value as the site uses it. The database may hand JSON back
     * with its keys reordered; a section whose order matters puts it right here.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public function fromStorage(array $value): array
    {
        return $value;
    }

    /**
     * Tidies the submitted fields before they are checked: trimmed text, empty list entries dropped.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function prepare(array $input): array
    {
        return $input;
    }

    /**
     * A field's value written out for the history.
     */
    public function describe(string $field, mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value)));
        }

        $text = trim((string) $value);

        return $text === '' ? '(empty)' : $text;
    }
}
