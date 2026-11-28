<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FeaturedMember;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('site.home', [
            'featuredMembers' => FeaturedMember::query()->visible()->ordered()->get(),
        ]);
    }
}
