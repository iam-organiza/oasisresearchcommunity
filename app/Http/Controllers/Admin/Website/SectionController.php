<?php

namespace App\Http\Controllers\Admin\Website;

use App\Content\Section;
use App\Content\SiteContentStore;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editing one section of the public site. Each section has its own page
 * (admin/website/{key}); saving goes live at once and is kept in the history.
 */
class SectionController extends Controller
{
    public function __construct(private SiteContentStore $store) {}

    public function edit(Request $request, string $section): Response
    {
        $definition = $this->authorised($request->user(), $section);

        return Inertia::render('admin/website/'.$definition->key(), [
            'values' => $this->store->get($definition->key()),
            'defaults' => $definition->defaults(),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $definition = $this->authorised($request->user(), $section);

        $value = Validator::make(
            $definition->prepare($request->all()),
            $definition->rules(),
            $definition->messages(),
        )->validate();

        $this->store->save($definition->key(), $value, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$definition->label()} saved. The site shows it now."]);

        return back();
    }

    private function authorised(?User $user, string $key): Section
    {
        $definition = $this->store->sections()[$key] ?? abort(404);

        abort_unless($user !== null && array_key_exists($key, $this->store->editableBy($user)), 403);

        return $definition;
    }
}
