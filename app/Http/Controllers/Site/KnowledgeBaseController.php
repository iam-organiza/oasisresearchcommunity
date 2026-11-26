<?php

namespace App\Http\Controllers\Site;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostComment;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function hub(): View
    {
        $pick = fn (string $flag, int $take): Collection => Post::query()
            ->live()->where($flag, true)->newest()->with('category')->take($take)->get();

        return view('site.knowledge-base', [
            'featured' => $pick('is_featured', 6),
            'editorsChoice' => $pick('is_editors_choice', 4),
            'popular' => $pick('is_popular', 4),
            'recent' => Post::query()->live()->newest()->with('category')->take(6)->get(),
            ...$this->sidebar(),
        ]);
    }

    public function index(Request $request): View
    {
        $category = $request->string('category')->trim()->value();
        $tag = $request->string('tag')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $posts = Post::query()
            ->live()
            ->with('category')
            ->when($category !== '', fn (Builder $q) => $q->whereHas(
                'category', fn (Builder $q) => $q->where('slug', $category),
            ))
            ->when($tag !== '', fn (Builder $q) => $q->whereHas(
                'tags', fn (Builder $q) => $q->where('slug', $tag),
            ))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function (Builder $q) use ($like): void {
                    $q->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhereHas('tags', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('category', fn (Builder $q) => $q->where('name', 'like', $like));
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('is_editors_choice')
            ->newest()
            ->paginate(9)
            ->withQueryString();

        return view('site.posts', [
            'posts' => $posts,
            'activeCategory' => $category === '' ? null : PostCategory::where('slug', $category)->first(),
            'activeTag' => $tag === '' ? null : Tag::where('slug', $tag)->first(),
            'search' => $search,
            ...$this->sidebar(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $post = Post::query()->where('slug', $slug)->with(['category', 'tags'])->firstOrFail();

        // Editors and admins can open a draft or private post to check it before it goes out.
        abort_unless($post->isLive() || $request->user()?->role->canManageContent(), 404);

        $comments = $post->comments()
            ->where('status', CommentStatus::Approved)
            ->oldest()
            ->get();

        $replyTo = $request->integer('reply') ?: null;

        return view('site.post', [
            'post' => $post,
            'comments' => $comments->whereNull('parent_id')->values(),
            'replies' => $comments->whereNotNull('parent_id')->groupBy('parent_id'),
            'commentCount' => $comments->count(),
            'replyTo' => $replyTo ? $comments->firstWhere('id', $replyTo) : null,
            ...$this->sidebar(),
        ]);
    }

    /**
     * The first site linked posts as /post?slug=...; those links are indexed
     * and shared, so they are sent on to the new address for good.
     */
    public function legacy(Request $request): RedirectResponse
    {
        $slug = $request->string('slug')->trim()->value();

        return $slug === ''
            ? redirect()->route('posts.index', status: 301)
            : redirect()->route('posts.show', $slug, 301);
    }

    public function storeComment(Request $request, string $slug): RedirectResponse
    {
        $post = Post::query()->where('slug', $slug)->firstOrFail();

        abort_unless($post->isLive() && $post->allow_comments, 403);

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($data['parent_id'])
            ? $post->comments()->where('status', CommentStatus::Approved)->whereKey($data['parent_id'])->first()
            : null;

        // "website" is a field people never see. Only automated form-fillers
        // complete it, and they get the same answer without anything saved.
        if (! $request->filled('website')) {
            $comment = new PostComment([
                'parent_id' => $parent?->id,
                'author_name' => $data['author_name'],
                'author_email' => $data['author_email'],
                'content' => $data['content'],
                'status' => CommentStatus::Pending,
            ]);
            $post->comments()->save($comment);
        }

        return redirect()
            ->to(route('posts.show', $post->slug).'#comments')
            ->with('comment_sent', true);
    }

    /**
     * @return array{categories: Collection<int, PostCategory>, popularTags: Collection<int, Tag>, recentPosts: Collection<int, Post>}
     */
    private function sidebar(): array
    {
        $live = function (Builder $q): void {
            /** @var Builder<Post> $q */
            $q->live();
        };

        return [
            'categories' => PostCategory::query()
                ->withCount(['posts' => $live])
                ->whereHas('posts', $live)
                ->orderBy('name')
                ->get(),
            'popularTags' => Tag::query()
                ->withCount(['posts' => $live])
                ->whereHas('posts', $live)
                ->orderByDesc('posts_count')
                ->orderBy('name')
                ->take(15)
                ->get(),
            'recentPosts' => Post::query()->live()->newest()->take(4)->get(),
        ];
    }
}
