<?php

namespace App\Http\Controllers\Admin\Exam;

use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Models\PrepMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exam prep materials: guides, slides, readings or links, by research area.
 * Files stay on the private disk. Applicants see the published ones once
 * their exam fee is paid (the exam PR).
 */
class PrepMaterialController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/exam/materials/index', [
            'materials' => PrepMaterial::query()->orderByRaw('area is null desc')->orderBy('area')->orderBy('title')->get()
                ->map(fn (PrepMaterial $material) => [
                    'id' => $material->id,
                    'title' => $material->title,
                    'area' => $material->area,
                    'area_label' => $material->areaLabel(),
                    'description' => $material->description,
                    'url' => $material->isFile() ? route('admin.exam.materials.show', $material) : $material->url,
                    'is_file' => $material->isFile(),
                    'file_name' => $material->original_name,
                    'size' => $material->size,
                    'published' => $material->published,
                ]),
            'areas' => collect(ApplicationSteps::AREAS)->map(fn (array $info, string $key) => ['value' => $key, 'label' => $info['label']])->values(),
            'maxMb' => (int) (PrepMaterial::MAX_KILOBYTES / 1024),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'area' => ['nullable', Rule::in(array_keys(ApplicationSteps::AREAS))],
            'description' => ['nullable', 'string', 'max:500'],
            'kind' => ['required', Rule::in(['file', 'link'])],
            'file' => ['nullable', 'required_if:kind,file', 'file', 'max:'.PrepMaterial::MAX_KILOBYTES, 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,mp4,mp3,jpg,jpeg,png'],
            'url' => ['nullable', 'required_if:kind,link', 'url:http,https', 'max:500'],
        ], [
            'title.required' => 'Give it a title members will recognise.',
            'file.required_if' => 'Choose the file to upload.',
            'file.max' => 'The file is too big; the most is '.(PrepMaterial::MAX_KILOBYTES / 1024).' MB. Share bigger files as a link.',
            'url.required_if' => 'Paste the link.',
            'url.url' => 'Paste a full link starting with https://',
        ]);

        $file = $data['kind'] === 'file' ? $request->file('file') : null;

        PrepMaterial::query()->create([
            'title' => $data['title'],
            'area' => $data['area'] ?? null,
            'description' => $data['description'] ?? null,
            'url' => $file ? null : $data['url'],
            'path' => $file?->store('prep-materials', 'local'),
            'original_name' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getMimeType(),
            'size' => $file?->getSize(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material added.']);

        return back();
    }

    public function update(Request $request, PrepMaterial $material): RedirectResponse
    {
        $data = $request->validate(['published' => ['required', 'boolean']]);
        $material->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['published'] ? 'Material published.' : 'Material hidden from applicants.']);

        return back();
    }

    public function destroy(PrepMaterial $material): RedirectResponse
    {
        $material->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material deleted.']);

        return back();
    }

    public function show(PrepMaterial $material): StreamedResponse
    {
        abort_unless($material->isFile() && Storage::disk('local')->exists((string) $material->path), 404);

        return Storage::disk('local')->download((string) $material->path, (string) $material->original_name);
    }
}
