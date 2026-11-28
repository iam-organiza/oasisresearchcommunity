<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = CommentStatus::tryFrom($request->string('status')->value()) ?? CommentStatus::Pending;

        $comments = PostComment::query()
            ->with(['post:id,title,slug', 'parent:id,author_name,content,status'])
            ->withCount(['replies as approved_replies_count' => fn (Builder $q) => $q->where('status', CommentStatus::Approved)])
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
                // What a reply answers, so it can be judged in context.
                'parent' => $comment->parent ? [
                    'author_name' => $comment->parent->author_name,
                    'excerpt' => Str::limit($comment->parent->content, 160),
                    'status' => $comment->parent->status->value,
                ] : null,
                'approved_replies' => (int) $comment->approved_replies_count,
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
        $data = $request->validate([
            'status' => ['required', Rule::enum(CommentStatus::class)],
            // From Undo: replies a rejection sent back to Waiting, to put up again.
            'reopen' => ['array'],
            'reopen.*' => ['integer'],
        ]);
        $previous = $comment->status;
        $next = CommentStatus::from($data['status']);

        // A reply shows only under an approved comment, so it cannot be approved on its own.
        if ($next === CommentStatus::Approved && $comment->parent !== null && $comment->parent->status !== CommentStatus::Approved) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Approve the comment by {$comment->parent->author_name} first. A reply shows only under an approved comment.",
            ]);

            return back();
        }

        $comment->update(['status' => $next]);

        // A reply should not stay visible under a comment that was taken down.
        $sentBack = [];
        if ($next !== CommentStatus::Approved) {
            $sentBack = PostComment::query()
                ->where('parent_id', $comment->id)
                ->where('status', CommentStatus::Approved)
                ->pluck('id')
                ->all();
            PostComment::query()->whereKey($sentBack)->update(['status' => CommentStatus::Pending]);
        }

        if ($next === CommentStatus::Approved && ! empty($data['reopen'])) {
            PostComment::query()
                ->where('parent_id', $comment->id)
                ->whereKey($data['reopen'])
                ->where('status', CommentStatus::Pending)
                ->update(['status' => CommentStatus::Approved]);
        }

        $replies = count($sentBack) === 1 ? 'Its reply went' : 'Its '.count($sentBack).' replies went';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match ($next) {
                CommentStatus::Approved => 'Approved. It is on the post now.',
                CommentStatus::Rejected => 'Rejected. It stays off the site.',
                CommentStatus::Pending => 'Moved back to Waiting.',
            }.($sentBack !== [] ? " {$replies} back to Waiting." : ''),
            // Undo puts it back as it was, replies included.
            'undo' => [
                'url' => route('admin.comments.update', $comment),
                'data' => ['status' => $previous->value, 'reopen' => $sentBack],
            ],
        ]);

        return back();
    }

    public function destroy(PostComment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comment deleted.']);

        return back();
    }
}
