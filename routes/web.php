<?php

use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\FeaturedMemberController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\KnowledgeBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
// their tools, members back to the site.
Route::get('admin', fn (Request $request) => $request->user()->role->isStaff()
    ? to_route('admin.posts.index')
    : to_route('home'))
    ->middleware('auth')
    ->name('admin');

Route::middleware(['auth', 'verified', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('posts', PostController::class)->except('show');
    Route::post('uploads/image', [PostController::class, 'uploadImage'])->name('uploads.image');
    Route::post('categories', [PostController::class, 'storeCategory'])->name('categories.store');

    Route::resource('featured-members', FeaturedMemberController::class)->except('show');

    Route::get('comments', [CommentController::class, 'index'])->name('comments.index');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
});

require __DIR__.'/settings.php';

// Unknown addresses still load the visitor's session, so the not-found page
// shows the right button for someone who is signed in.
Route::fallback(fn () => abort(404));
