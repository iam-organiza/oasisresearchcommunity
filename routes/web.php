<?php

use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\FeaturedMemberController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Membership\ApplicationController;
use App\Http\Controllers\Membership\ApplicationDocumentController;
use App\Http\Controllers\Membership\MembershipController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\KnowledgeBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('knowledge-base', [KnowledgeBaseController::class, 'hub'])->name('knowledge-base');
Route::get('posts', [KnowledgeBaseController::class, 'index'])->name('posts.index');
Route::get('knowledge-base/{slug}', [KnowledgeBaseController::class, 'show'])->name('posts.show');
Route::post('knowledge-base/{slug}/comments', [KnowledgeBaseController::class, 'storeComment'])
    ->middleware('throttle:5,1')
    ->name('posts.comments.store');
Route::view('terms-conditions', 'site.terms')->name('terms');

// Addresses used by the first site.
Route::get('post', [KnowledgeBaseController::class, 'legacy']);
Route::permanentRedirect('blog', '/knowledge-base');
Route::permanentRedirect('welcome', '/');
Route::permanentRedirect('signin', '/login');
Route::permanentRedirect('signup', '/register');

// Where people land after logging in, as on the first site: staff go to
// their tools, members back to the site. The site is not a React page, so
// members are sent there as a full page visit; a plain redirect would make
// the React pages (log in, confirm password) show it in a pop-up.
// Each staff role lands on its own area: editors and admins on the posts,
// the registrar on the chapters (the member register comes next).
Route::get('admin', fn (Request $request) => match (true) {
    $request->user()->role->canManageContent() => to_route('admin.posts.index'),
    $request->user()->role->canManageMembers() => to_route('admin.chapters.index'),
    // Straight after verifying their email, members choose: apply for membership or carry on reading.
    $request->has('verified') => to_route('membership.show'),
    default => Inertia::location(route('home')),
})
    ->middleware('auth')
    ->name('admin');

Route::middleware(['auth', 'verified', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('can:manage-content')->group(function () {
        Route::resource('posts', PostController::class)->except('show');
        Route::post('uploads/image', [PostController::class, 'uploadImage'])->name('uploads.image');
        Route::post('categories', [PostController::class, 'storeCategory'])->name('categories.store');

        Route::resource('featured-members', FeaturedMemberController::class)->except('show');

        Route::get('comments', [CommentController::class, 'index'])->name('comments.index');
        Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    });

    Route::middleware('can:manage-members')->group(function () {
        Route::get('chapters', ChapterController::class)->name('chapters.index');
    });

    Route::middleware('can:manage-staff')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    });
});

// A member's own membership: their application now, their grade and number once approved.
Route::middleware(['auth', 'verified'])->prefix('membership')->name('membership.')->group(function () {
    Route::get('/', [MembershipController::class, 'show'])->name('show');
    Route::post('apply', [ApplicationController::class, 'start'])->name('apply.start');
    Route::post('apply/submit', [ApplicationController::class, 'submit'])->name('apply.submit');
    Route::get('apply/{step}', [ApplicationController::class, 'edit'])->name('apply.edit');
    Route::put('apply/{step}', [ApplicationController::class, 'update'])->name('apply.update');

    Route::post('documents', [ApplicationDocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [ApplicationDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('documents/{document}', [ApplicationDocumentController::class, 'show'])->name('documents.show');
});

require __DIR__.'/settings.php';

// Unknown addresses still load the visitor's session, so the not-found page
// shows the right button for someone who is signed in.
Route::fallback(fn () => abort(404));
