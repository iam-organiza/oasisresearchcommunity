<?php

namespace App\Http\Controllers\Membership;

use App\Enums\Membership\DocumentKind;
use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\MembershipApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CVs, certificates and other evidence. Files go to the private `local`
 * disk, never to public/, and are only handed to the applicant or to staff
 * who manage membership.
 */
class ApplicationDocumentController extends Controller
{
    public const MAX_DOCUMENTS = 10;

    public const MAX_KILOBYTES = 5120;

    public function store(Request $request): RedirectResponse
    {
        $application = $this->editableApplication($request);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.mimes' => 'Upload a PDF, JPG or PNG file.',
            'file.max' => 'Each file must be 5 MB or smaller.',
            'file.uploaded' => 'The file could not be uploaded. It may be larger than 5 MB.',
        ]);

        if ($application->documents()->count() >= self::MAX_DOCUMENTS) {
            throw ValidationException::withMessages([
                'file' => 'You can upload up to '.self::MAX_DOCUMENTS.' files. Remove one to add another.',
            ]);
        }

        $file = $data['file'];
        $path = $file->store("applications/{$application->id}", 'local');

        $application->documents()->create([
            'kind' => $data['kind'],
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'File uploaded.']);

        return back();
    }

    public function destroy(Request $request, ApplicationDocument $document): RedirectResponse
    {
        $application = $this->editableApplication($request);
        abort_unless($document->membership_application_id === $application->id, 404);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'File removed.']);

        return back();
    }

    /**
     * Download a document: its applicant, or staff who manage membership.
     */
    public function show(Request $request, ApplicationDocument $document): StreamedResponse
    {
        $owner = $document->application->user_id === $request->user()->id;
        abort_unless($owner || Gate::allows('manage-members'), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function editableApplication(Request $request): MembershipApplication
    {
        $application = $request->user()->latestApplication;
        abort_unless($application && $application->status->isEditable(), 403);

        return $application;
    }
}
