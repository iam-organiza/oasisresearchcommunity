<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeaturedMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FeaturedMemberController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/featured-members/index', [
            'members' => FeaturedMember::query()->orderBy('id')->get()
                ->map(fn (FeaturedMember $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'position' => $member->position,
                    'job_description' => $member->job_description,
                    'avatar_url' => $member->avatar_url,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/featured-members/form', ['member' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save(new FeaturedMember, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Featured member added.']);

        return to_route('admin.featured-members.index');
    }

    public function edit(FeaturedMember $featuredMember): Response
    {
        return Inertia::render('admin/featured-members/form', [
            'member' => [
                ...$featuredMember->only([
                    'id', 'title', 'first_name', 'middle_name', 'last_name',
                    'position', 'speciality', 'job_description',
                ]),
                'avatar_url' => $featuredMember->avatar_url,
            ],
        ]);
    }

    public function update(Request $request, FeaturedMember $featuredMember): RedirectResponse
    {
        $this->save($featuredMember, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Featured member saved.']);

        return to_route('admin.featured-members.index');
    }

    public function destroy(FeaturedMember $featuredMember): RedirectResponse
    {
        $featuredMember->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Featured member removed.']);

        return to_route('admin.featured-members.index');
    }

    private function save(FeaturedMember $member, Request $request): void
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:36'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'speciality' => ['nullable', 'string', 'max:100'],
            'job_description' => ['nullable', 'string', 'max:2000'],
            'avatar_file' => [
                // A photo is needed the first time; after that it is kept
                // unless a new one is chosen.
                $member->avatar ? 'nullable' : 'required',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
            ],
        ], [
            'avatar_file.required' => 'Choose a photo.',
            'avatar_file.max' => 'The photo must be 5 MB or smaller.',
        ]);

        $file = $request->file('avatar_file');
        unset($data['avatar_file']);

        if ($file instanceof UploadedFile) {
            $name = 'avatar_'.Str::lower(Str::random(20)).'.'.$file->extension();
            Storage::disk('media')->putFileAs('avatars', $file, $name);
            $data['avatar'] = 'assets/media/avatars/'.$name;
        }

        $member->fill($data)->save();
    }
}
