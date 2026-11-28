<?php

namespace App\Content;

use App\Models\SiteContent;
use App\Models\SiteContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads, saves and restores the public site's editable sections. A section
 * nobody has saved shows its built-in text; every save and restore is kept
 * as a revision, so any version can be put back.
 */
class SiteContentStore
{
    /**
     * @return array<string, Section>
     */
    public function sections(): array
    {
        return collect([
            new Home\HeroSection,
            new Home\ServicesSection,
            new Home\BenefitsSection,
            new Home\CategoriesSection,
            new Home\JoinSection,
            new Home\AboutSection,
            new SearchSection(
                key: 'home.search',
                page: 'home',
                pageName: 'Home page',
                defaultTitle: 'OASIS Research Community',
                defaultDescription: 'A professional body established to optimize the practice of research in Nigeria through training, publishing, consulting and membership.',
            ),
            new KnowledgeBaseIntroSection,
            new SearchSection(
                key: 'knowledge-base.search',
                page: 'knowledge-base',
                pageName: 'Knowledge Base',
                defaultTitle: 'Knowledge Base - OASIS Research Community',
                defaultDescription: 'Research insights, methods and practical guides from the OASIS Research Community.',
            ),
            new ContactSection,
        ])
            ->keyBy(fn (Section $section) => $section->key())
            ->all();
    }

    public function section(string $key): Section
    {
        return $this->sections()[$key] ?? throw new \InvalidArgumentException("No site section called {$key}.");
    }

    /**
     * The sections on one editing page that this person may change, in page order.
     *
     * @return array<string, Section>
     */
    public function pageFor(User $user, string $page): array
    {
        return array_filter($this->editableBy($user), fn (Section $section) => $section->page() === $page);
    }

    /**
     * Every section of a page, as the public site shows them.
     *
     * @return array<string, array<string, mixed>>
     */
    public function page(string $page): array
    {
        return collect($this->sections())
            ->filter(fn (Section $section) => $section->page() === $page)
            ->mapWithKeys(fn (Section $section, string $key) => [$key => $this->get($key)])
            ->all();
    }

    /**
     * The sections this person may change.
     *
     * @return array<string, Section>
     */
    public function editableBy(User $user): array
    {
        return array_filter($this->sections(), fn (Section $section) => $user->role->canManageContent()
            && (! $section->adminOnly() || $user->role->canManageStaff()));
    }

    /**
     * The section as the site shows it: the saved fields, with the built-in text for any not saved.
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $section = $this->section($key);

        $saved = Cache::rememberForever(self::cacheKey($key), fn () => SiteContent::query()->where('key', $key)->value('value'));

        return array_replace($section->defaults(), is_array($saved) ? $section->fromStorage($saved) : []);
    }

    /**
     * @param  array<string, mixed>  $value  Already checked against the section's rules.
     */
    public function save(string $key, array $value, ?User $by, string $action = 'saved', ?int $restoredFrom = null): SiteContentRevision
    {
        $this->section($key);

        $revision = DB::transaction(function () use ($key, $value, $by, $action, $restoredFrom): SiteContentRevision {
            SiteContent::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->id]);

            return SiteContentRevision::query()->create([
                'key' => $key,
                'value' => $value,
                'user_id' => $by?->id,
                'action' => $action,
                'restored_from_id' => $restoredFrom,
            ]);
        });

        Cache::forget(self::cacheKey($key));
        $this->applyToConfig();

        return $revision;
    }

    /**
     * Put back an earlier version, or the built-in text when no revision is given. The restore is itself a revision.
     */
    public function restore(string $key, ?SiteContentRevision $revision, ?User $by): SiteContentRevision
    {
        $value = $revision->value ?? $this->section($key)->defaults();

        return $this->save($key, $value, $by, 'restored', $revision?->id);
    }

    /**
     * The rest of the app reads contact details from config('oasis'); the
     * saved ones are laid over the built-in file once, as the app starts.
     * Before the tables exist (a fresh install, some tests) the file's values stay.
     */
    public function applyToConfig(): void
    {
        if (config('oasis._defaults') === null) {
            config(['oasis._defaults' => config('oasis')]);
        }

        try {
            $contact = $this->get('contact');
        } catch (Throwable) {
            return;
        }

        config([
            'oasis.tagline' => $contact['tagline'],
            'oasis.support_email' => $contact['support_email'],
            'oasis.phones' => $contact['phones'],
            'oasis.address' => $contact['address'],
            'oasis.map_embed_url' => $contact['map_embed_url'],
            'oasis.social' => array_filter($contact['social']),
        ]);
    }

    private static function cacheKey(string $key): string
    {
        return "site_content.{$key}";
    }
}
