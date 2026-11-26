<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Inertia\Inertia;
use Inertia\Response;

class ChapterController extends Controller
{
    /**
     * The six regional chapters with the state chapters in each.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/chapters/index', [
            'regions' => Region::query()
                ->orderBy('name')
                ->with('states:id,region_id,name')
                ->get(['id', 'name'])
                ->map(fn (Region $region) => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'states' => $region->states->map->only(['id', 'name'])->values(),
                ]),
        ]);
    }
}
