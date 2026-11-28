<?php

namespace App\Http\Controllers\Site;

use App\Content\SiteContentStore;
use App\Http\Controllers\Controller;
use App\Models\FeaturedMember;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SiteContentStore $content): View
    {
        return view('site.home', [
            // The sections edited on Website → Home page, or their built-in text.
            'home' => $content->page('home'),
            'featuredMembers' => FeaturedMember::query()->visible()->ordered()->get(),
        ]);
    }
}
