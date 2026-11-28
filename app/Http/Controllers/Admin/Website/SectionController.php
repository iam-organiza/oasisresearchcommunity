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
 * Editing the public site's sections. A page (admin/website/{page}) holds
 * one or more sections, each saved on its own; saving goes live at once and
 * is kept in the history.
 */
class SectionController extends Controller
{
    public function __construct(private SiteContentStore $store) {}

    /**
     * One editing page (Home page, Contact & social), with every section on it this person may change.
     */
    public function edit(Request $request, string $page): Response
    {
        /** @var User $user */
        $user = $request->user();
        $sections = $this->store->pageFor($user, $page);

        if ($sections === []) {
            // A page that exists but is not theirs, or no such page.
            abort(collect($this->store->sections())->contains(fn (Section $s) => $s->page() === $page) ? 403 : 404);
        }

        return Inertia::render('admin/website/'.$page, [
            'sections' => collect($sections)->map(fn (Section $section, string $key) => [
                'key' => $key,
                'label' => $section->label(),
                'hint' => $section->hint(),
                'values' => $this->store->get($key),
            ])->values(),
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
