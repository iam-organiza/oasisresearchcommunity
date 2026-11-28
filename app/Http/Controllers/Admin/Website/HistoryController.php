<?php

namespace App\Http\Controllers\Admin\Website;

use App\Content\SiteContentStore;
use App\Http\Controllers\Controller;
use App\Models\SiteContentRevision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every change to the public site's sections: who, when, what changed, and
 * a way to put any version (or the original text) back.
 */
class HistoryController extends Controller
{
    public function __construct(private SiteContentStore $store) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $sections = $this->store->editableBy($user);
        $current = collect($sections)->map(fn ($section, $key) => $this->store->get($key));

        $revisions = SiteContentRevision::query()
            ->with('user:id,first_name,last_name')
            ->whereIn('key', array_keys($sections))
            ->latest('id')
            ->paginate(20);

        // What each revision changed, against the one before it (or the original text).
        $entries = $revisions->getCollection()->map(function (SiteContentRevision $revision) use ($sections, $current): array {
            $section = $sections[$revision->key];
            $previous = SiteContentRevision::query()
                ->where('key', $revision->key)
                ->where('id', '<', $revision->id)
                ->latest('id')
                ->first();
            $before = $previous ? $section->fromStorage($previous->value) : $section->defaults();
            $after = $section->fromStorage($revision->value);

            $changes = [];
            foreach ($section->fieldLabels() as $field => $label) {
                $old = $before[$field] ?? null;
                $new = $after[$field] ?? null;

                if ($old != $new) {
                    $changes[] = ['field' => $label, 'before' => $section->describe($field, $old), 'after' => $section->describe($field, $new)];
                }
            }

            return [
                'id' => $revision->id,
                'section' => $section->label(),
                'section_key' => $revision->key,
                'page' => $section->page(),
                'action' => $revision->action,
                'by' => $revision->user->name ?? 'Someone no longer on the staff',
                'when' => $revision->created_at?->format('j M Y, g:i a'),
                'changes' => $changes,
                // Restoring the version the site already shows would change nothing.
                'is_current' => $after == $current[$revision->key],
            ];
        });

        return Inertia::render('admin/website/history', [
            'entries' => $revisions->setCollection($entries),
            'sections' => collect($sections)->map(fn ($section, $key) => [
                'key' => $key,
                'label' => $section->label(),
                'is_original' => $current[$key] == $section->defaults(),
            ])->values(),
        ]);
    }

    public function restore(Request $request, SiteContentRevision $revision): RedirectResponse
    {
        $section = $this->editable($request, $revision->key);

        $this->store->restore($revision->key, $revision, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$section} is back to the version from {$revision->created_at?->format('j M Y, g:i a')}."]);

        return back();
    }

    public function restoreOriginal(Request $request, string $section): RedirectResponse
    {
        $label = $this->editable($request, $section);

        $this->store->restore($section, null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$label} is back to the original text."]);

        return back();
    }

    private function editable(Request $request, string $key): string
    {
        /** @var User $user */
        $user = $request->user();
        $section = $this->store->editableBy($user)[$key] ?? abort(403);

        return $section->label();
    }
}
