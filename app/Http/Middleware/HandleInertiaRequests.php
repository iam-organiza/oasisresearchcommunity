<?php

namespace App\Http\Middleware;

use App\Enums\CommentStatus;
use App\Models\PostComment;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'tagline' => config('oasis.tagline'),
            'auth' => [
                'user' => $request->user(),
                // Which staff areas this person may open, for the sidebar.
                'can' => [
                    'manageContent' => (bool) $request->user()?->role->canManageContent(),
                    'manageMembers' => (bool) $request->user()?->role->canManageMembers(),
                    'manageStaff' => (bool) $request->user()?->role->canManageStaff(),
                ],
            ],
            // Comments waiting for a decision, for the count beside "Comments" in the sidebar.
            'pendingComments' => fn () => $request->user()?->role->canManageContent()
                ? PostComment::query()->where('status', CommentStatus::Pending)->count()
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
