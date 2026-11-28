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
                ->has('sections', 1)
                ->where('sections.0.values.support_email', 'support@oasisresearchcommunity.org'));

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
                ->where('sections.9.key', 'contact')
                ->where('sections.9.is_original', false)
                ->where('sections.0.is_original', true));

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
        $this->put(route('admin.website.update', 'home.join'), ['headline' => 'Join us today']);

        $this->actingAs($this->staff(UserRole::Editor));
        // Editors see the home page change, not the contact one.
        $this->get(route('admin.website.history'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.section', 'Join Us banner')
                ->where('entries.data.0.page', 'home'));
        $this->post(route('admin.website.history.restore', $revision))->assertForbidden();
        $this->post(route('admin.website.restore-original', 'contact'))->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.website.history'))->assertForbidden();
    }

    public function test_editors_edit_the_home_page_section_by_section(): void
    {
        $this->actingAs($this->staff(UserRole::Editor))
            ->get(route('admin.website.edit', 'home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/website/home')
                ->has('sections', 7)
                ->where('sections.0.key', 'home.hero')
                ->where('sections.5.values.values', ['Excellence', 'Ethics', 'Integrity']));

        $this->put(route('admin.website.update', 'home.hero'), ['intro' => "Research that changes things.\n\nFor everyone."])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Opening saved. The site shows it now.');

        $this->put(route('admin.website.update', 'home.about'), [
            'intro' => 'About us.', 'values' => ['Rigour.', '  ', 'Openness'], 'vision' => 'A vision', 'mission' => 'A mission', 'recognition' => 'Registered.',
        ])->assertSessionHasNoErrors();

        $this->put(route('admin.website.update', 'home.benefits'), [
            'intro' => 'Benefits.', 'benefits' => [['title' => 'Grants', 'text' => 'News of grants'], ['title' => '', 'text' => '']],
        ])->assertSessionHasNoErrors();

        $this->get(route('home'))
            ->assertSee('Research that changes things.<br />', false)
            ->assertSee('Rigour.')
            ->assertSee('Openness.')
            ->assertDontSee('Integrity.')
            ->assertSee('News of grants')
            ->assertDontSee('Paid participation on research consulting')
            // Untouched sections keep their built-in text.
            ->assertSee('Join Our Community of Research Professionals to Stay Ahead');
    }

    public function test_the_home_page_shows_todays_text_until_edited(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('We believe that development at local, national or global levels')
            ->assertSeeInOrder(['Training', 'Publishing', 'Consulting', 'Membership'])
            ->assertSeeInOrder(['Information', 'Payment', 'Services'])
            ->assertSeeInOrder(['Excellence.', 'Ethics.', 'Integrity.'])
            ->assertSee('Universities, research institutes, companies and other organizations.')
            ->assertSee('RC No:2008295');
    }

    public function test_home_sections_keep_to_the_layout(): void
    {
        $this->actingAs($this->staff(UserRole::Editor));

        $this->put(route('admin.website.update', 'home.services'), [
            'intro' => 'x',
            'services' => [['title' => str_repeat('a', 25), 'text' => 'x'], ['title' => 'B', 'text' => '']],
        ])->assertSessionHasErrors([
            'services.0.title' => 'Keep the name under 24 characters, so it fits the page in large type.',
            'services.1.text' => 'Say what the service is.',
            // Always four: the missing ones are blank and must be filled.
            'services.2.title' => 'Give the service a name.',
        ]);

        $this->put(route('admin.website.update', 'home.about'), [
            'intro' => 'x', 'values' => ['One', 'Two', 'Three', 'Four', 'Five'], 'vision' => 'x', 'mission' => 'x', 'recognition' => 'x',
        ])->assertSessionHasErrors(['values' => 'Give up to four core values; they are set very large across one line.']);

        $this->put(route('admin.website.update', 'home.join'), ['headline' => ''])
            ->assertSessionHasErrors(['headline' => 'Write the headline.']);

        $this->get(route('admin.website.edit', 'nowhere'))->assertNotFound();
        $this->get(route('admin.website.edit', 'contact'))->assertForbidden();
    }

    public function test_the_search_titles_and_knowledge_base_intro_are_editable(): void
    {
        // Today's titles and descriptions until someone edits them.
        $this->get(route('home'))
            ->assertSee('<title>OASIS Research Community</title>', false)
            ->assertSee('content="A professional body established to optimize the practice of research', false);
        $this->get(route('knowledge-base'))->assertSee('<title>Knowledge Base - OASIS Research Community</title>', false);

        $this->actingAs($this->staff(UserRole::Editor));
        $this->get(route('admin.website.edit', 'knowledge-base'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/website/knowledge-base')
                ->where('sections.0.key', 'knowledge-base.intro')
                ->where('sections.1.label', 'Knowledge Base in search results'));

        $this->put(route('admin.website.update', 'home.search'), ['title' => 'OASIS Research Community | Research training in Nigeria', 'description' => "Training,   publishing\nand membership."])
            ->assertSessionHasNoErrors();
        $this->put(route('admin.website.update', 'knowledge-base.search'), ['title' => 'Research guides - OASIS', 'description' => 'Guides on research methods.'])
            ->assertSessionHasNoErrors();
        $this->put(route('admin.website.update', 'knowledge-base.intro'), ['intro' => 'Guides for researchers & students.'])
            ->assertSessionHasNoErrors();

        $this->get(route('home'))
            ->assertSee('<title>OASIS Research Community | Research training in Nigeria</title>', false)
            ->assertSee('<meta name="description" content="Training, publishing and membership.">', false)
            ->assertSee('<meta property="og:title" content="OASIS Research Community | Research training in Nigeria">', false);
        $this->get(route('knowledge-base'))
            ->assertSee('<title>Research guides - OASIS</title>', false)
            ->assertSee('Guides for researchers &amp; students.', false);

        $this->put(route('admin.website.update', 'home.search'), ['title' => str_repeat('a', 61), 'description' => ''])
            ->assertSessionHasErrors([
                'title' => 'Keep the title under 60 characters; search engines cut longer ones short.',
                'description' => 'Write the description search engines show.',
            ]);
    }

    public function test_admins_edit_the_legal_pages_and_editors_cannot(): void
    {
        $this->actingAs($this->staff(UserRole::Editor))
            ->get(route('admin.website.edit', 'legal'))->assertForbidden();
        $this->put(route('admin.website.update', 'legal.privacy'), ['body' => '<p>x</p>'])->assertForbidden();

        $this->actingAs($this->staff(UserRole::Admin))
            ->get(route('admin.website.edit', 'legal'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/website/legal')
                ->where('sections.0.key', 'legal.terms')
                ->where('sections.1.values.updated', '27 November 2026'));
    }

    public function test_a_saved_legal_page_is_cleaned_dated_and_keeps_following_the_contact_details(): void
    {
        $this->actingAs($this->staff(UserRole::Admin));
        $this->travelTo(now()->setDate(2027, 2, 3));

        $this->put(route('admin.website.update', 'legal.privacy'), [
            'body' => '<h3>1. Who we are</h3><p class="policy-promise">Write to <a href="mailto:support@oasisresearchcommunity.org">support@oasisresearchcommunity.org</a>.</p><script>alert(1)</script>',
            'updated' => '27 November 2026',
            'touch_updated' => true,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Privacy Policy saved. The site shows it now.');

        $this->get(route('privacy'))
            ->assertSee('Last updated 3 February 2027')
            ->assertSee('<p class="policy-promise">', false)
            ->assertSee('href="#clause-1"', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        // The email in the text follows Contact & social.
        $this->put(route('admin.website.update', 'contact'), $this->contact(['support_email' => 'privacy@oasis.test']))->assertSessionHasNoErrors();
        $this->get(route('privacy'))
            ->assertSee('privacy@oasis.test')
            ->assertDontSee('support@oasisresearchcommunity.org');

        // A small correction can keep the date.
        $this->put(route('admin.website.update', 'legal.privacy'), ['body' => '<h3>1. Who we are</h3><p>Corrected.</p>', 'updated' => '3 February 2027', 'touch_updated' => false])
            ->assertSessionHasNoErrors();
        $this->get(route('privacy'))->assertSee('Last updated 3 February 2027')->assertSee('Corrected.');

        // The history counts words rather than repeating the whole text, and the original can come back.
        $this->get(route('admin.website.history'))
            ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.changes.0', ['field' => 'Text', 'before' => '7 words', 'after' => '3 words']));
        $this->post(route('admin.website.restore-original', 'legal.privacy'))->assertSessionHasNoErrors();
        $this->get(route('privacy'))
            ->assertSee('Last updated 27 November 2026')
            ->assertSee('Nigeria Data Protection Act')
            ->assertSee('privacy@oasis.test');
    }
}
