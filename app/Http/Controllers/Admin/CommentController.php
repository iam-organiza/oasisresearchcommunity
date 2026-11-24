<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\PostComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = CommentStatus::tryFrom($request->string('status')->value()) ?? CommentStatus::Pending;

        $comments = PostComment::query()
            ->with('post:id,title,slug')
            ->where('status', $status)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PostComment $comment): array => [
                'id' => $comment->id,
                'author_name' => $comment->author_name,
                'author_email' => $comment->author_email,
                'content' => $comment->content,
                'status' => $comment->status->value,
                'is_reply' => $comment->parent_id !== null,
                'date' => $comment->created_at?->format('j M Y, g:i a'),
                'post_title' => $comment->post->title,
                'post_slug' => $comment->post->slug,
            ]);

        return Inertia::render('admin/comments/index', [
            'comments' => $comments,
            'status' => $status->value,
            'counts' => collect(CommentStatus::cases())->mapWithKeys(fn (CommentStatus $s): array => [
                $s->value => PostComment::query()->where('status', $s)->count(),
            ]),
        ]);
    }

    public function update(Request $request, PostComment $comment): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(CommentStatus::class)]]);

        $comment->update(['status' => $data['status']]);

        // A reply should not stay visible under a comment that was taken down.
        if ($comment->status !== CommentStatus::Approved) {
            PostComment::query()
                ->where('parent_id', $comment->id)
                ->where('status', CommentStatus::Approved)
                ->update(['status' => CommentStatus::Pending]);
        }

        return back();
    }

    public function destroy(PostComment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comment deleted.']);

        return back();
    }
}
