<?php

namespace App\Http\Controllers\Site;

use App\Content\SiteContentStore;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Terms & Conditions and the Privacy Policy: their text and "Last updated"
 * date come from Website → Legal pages, or the built-in text until edited.
 */
class LegalPageController extends Controller
{
    public function __construct(private SiteContentStore $content) {}

    public function terms(): View
    {
        return $this->page('legal.terms', 'Terms & Conditions', 'Terms and Conditions for OASIS Research Community. Learn about our guidelines, user responsibilities, and legal agreements.');
    }

    public function privacy(): View
    {
        return $this->page('legal.privacy', 'Privacy Policy', 'How OASIS Research Community collects, uses, shares and keeps your personal data, and your rights under the Nigeria Data Protection Act 2023.');
    }

    private function page(string $key, string $title, string $description): View
    {
        $text = $this->content->get($key);

        return view('site.document', [
            'title' => $title,
            'description' => $description,
            'html' => (string) $text['body'],
            'updated' => $text['updated'] ?? null,
        ]);
    }
}
