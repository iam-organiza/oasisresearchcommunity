<?php

namespace Tests\Feature\Admin;

use App\Content\SiteContentStore;
use App\Enums\UserRole;
use App\Models\SiteContentRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    private const MAP = 'https://www.google.com/maps/embed?pb=!1m18!new-office';

    private function staff(UserRole $role): User
    {
        $user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Okoro']);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contact(array $overrides = []): array
    {
        return array_replace_recursive([
            'tagline' => 'Research that delivers',
            'support_email' => 'Hello@Oasis.test',
            'phones' => ['+234 800 000 0001', '', ''],
            'address' => ['Head Office', '1 Example Road,', 'Abuja, Nigeria'],
            'map_embed_url' => '<iframe src="'.self::MAP.'&amp;z=1" width="600"></iframe>',
            'social' => ['Facebook' => 'https://www.facebook.com/oasis', 'Instagram' => '', 'LinkedIn' => 'https://www.linkedin.com/company/oasis', 'WhatsApp' => ''],
        ], $overrides);
    }

    public function test_only_admins_edit_the_contact_details(): void
    {
        $this->actingAs($this->staff(UserRole::Editor))
            ->get(route('admin.website.edit', 'contact'))->assertForbidden();
        $this->put(route('admin.website.update', 'contact'), $this->contact())->assertForbidden();

        $this->actingAs($this->staff(UserRole::Admin))
            ->get(route('admin.website.edit', 'contact'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/website/contact')
                ->where('values.support_email', 'support@oasisresearchcommunity.org'));

        $this->get(route('admin.website.edit', 'nothing-here'))->assertNotFound();
    }

    public function test_saved_details_replace_the_built_in_ones_everywhere(): void
    {
        $this->get(route('home'))->assertSee('support@oasisresearchcommunity.org');

        $this->actingAs($this->staff(UserRole::Admin))
            ->put(route('admin.website.update', 'contact'), $this->contact())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Contact & social saved. The site shows it now.');

        // The map's address is kept from the pasted code, the email tidied, empty boxes dropped.
        $saved = app(SiteContentStore::class)->get('contact');
        $this->assertSame(self::MAP.'&z=1', $saved['map_embed_url']);
        $this->assertSame('hello@oasis.test', $saved['support_email']);
        $this->assertSame(['+234 800 000 0001'], $saved['phones']);
        // The icons keep their order whatever order the database returns them in.
        $this->assertSame(['Facebook', 'Instagram', 'LinkedIn', 'WhatsApp'], array_keys($saved['social']));

        $this->get(route('home'))
            ->assertSee('Research that delivers')
            ->assertSee('hello@oasis.test')
            ->assertSee('+234 800 000 0001')
            ->assertSee('Head Office')
            ->assertSee('https://www.facebook.com/oasis')
            ->assertDontSee('instagram.com/oasisresearchcommunity');
        $this->get(route('privacy'))->assertSee('hello@oasis.test');
    }

    public function test_links_must_be_what_they_say(): void
    {
        $this->actingAs($this->staff(UserRole::Admin))
            ->put(route('admin.website.update', 'contact'), $this->contact([
                'tagline' => '',
                'phones' => ['call us', '', ''],
                'map_embed_url' => 'https://example.com/map',
                'social' => ['Facebook' => 'https://evil.example/oasis'],
            ]))
            ->assertSessionHasErrors([
                'tagline' => 'Enter the tagline.',
                'phones.0' => 'Use digits, spaces and a leading + only, like +234 703 635 6635.',
                'map_embed_url' => 'Paste the link from Google Maps: Share, then Embed a map, then Copy HTML.',
                'social.Facebook' => 'That is not a Facebook link. It should start https://facebook.com/',
            ]);

        $this->assertSame(0, SiteContentRevision::count());
    }

    public function test_the_history_shows_each_change_and_can_put_versions_back(): void
    {
        $admin = $this->staff(UserRole::Admin);
        $this->actingAs($admin);

        $this->put(route('admin.website.update', 'contact'), $this->contact(['tagline' => 'First']));
        $this->put(route('admin.website.update', 'contact'), $this->contact(['tagline' => 'Second']));
        [$first, $second] = SiteContentRevision::query()->orderBy('id')->get()->all();

        $this->get(route('admin.website.history'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.data.0.section', 'Contact & social')
                ->where('entries.data.0.by', 'Ada Okoro')
                ->where('entries.data.0.is_current', true)
                ->where('entries.data.0.changes', [['field' => 'Tagline', 'before' => 'First', 'after' => 'Second']])
                ->where('entries.data.1.is_current', false)
                ->where('sections.0.is_original', false));

        $this->post(route('admin.website.history.restore', $first))
            ->assertSessionHas('inertia.flash_data.toast.message', 'Contact & social is back to the version from '.$first->created_at?->format('j M Y, g:i a').'.');
        $this->assertSame('First', app(SiteContentStore::class)->get('contact')['tagline']);

        $this->post(route('admin.website.restore-original', 'contact'))
            ->assertSessionHas('inertia.flash_data.toast.message', 'Contact & social is back to the original text.');
        $this->assertSame('Optimizing Outcomes', app(SiteContentStore::class)->get('contact')['tagline']);
        $this->get(route('home'))->assertSee('support@oasisresearchcommunity.org');

        // Both restores are in the history too, as restores.
        $this->assertSame(['saved', 'saved', 'restored', 'restored'], SiteContentRevision::query()->orderBy('id')->pluck('action')->all());
        $this->assertSame($first->id, SiteContentRevision::query()->latest('id')->skip(1)->value('restored_from_id'));
        $this->assertNotNull($second);
    }

    public function test_editors_do_not_see_or_restore_admin_only_sections(): void
    {
        $this->actingAs($this->staff(UserRole::Admin))
            ->put(route('admin.website.update', 'contact'), $this->contact());
        $revision = SiteContentRevision::firstOrFail();

        $this->actingAs($this->staff(UserRole::Editor));
        $this->get(route('admin.website.history'))
            ->assertInertia(fn (Assert $page) => $page->where('entries.data', []));
        $this->post(route('admin.website.history.restore', $revision))->assertForbidden();
        $this->post(route('admin.website.restore-original', 'contact'))->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.website.history'))->assertForbidden();
    }
}
