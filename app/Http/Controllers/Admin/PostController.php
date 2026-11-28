<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Support\ArticleHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $status = PostStatus::tryFrom($request->string('status')->value());

        $posts = Post::query()
            ->with('category:id,name')
            ->withCount('comments')
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->newest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'visibility' => $post->visibility->value,
                'category' => $post->category?->name,
                'author_name' => $post->author_name,
                'comments_count' => $post->comments_count,
                'is_live' => $post->isLive(),
                'date' => $post->displayDate()?->format('j M Y'),
            ]);

        return Inertia::render('admin/posts/index', [
            'posts' => $posts,
            'filters' => ['search' => $search, 'status' => $status === null ? '' : $status->value],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/posts/form', [
            'post' => null,
            'defaultAuthor' => $request->user()->name,
            'categories' => $this->categories(),
        ]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = new Post;
        $post->author_id = $request->user()->id;
        $this->save($post, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->savedMessage($post, wasLive: false)]);

        return to_route('admin.posts.edit', $post);
    }

    public function edit(Post $post): Response
    {
        return Inertia::render('admin/posts/form', [
            'post' => [
                ...$post->only([
                    'id', 'title', 'slug', 'excerpt', 'content', 'author_name',
                    'post_category_id', 'reading_time', 'allow_comments',
                    'is_featured', 'is_editors_choice', 'is_popular', 'meta_title',
                    'meta_description', 'canonical_url', 'og_title', 'og_description',
                ]),
                'status' => $post->status->value,
                'visibility' => $post->visibility->value,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'tags' => $post->tags()->orderBy('name')->pluck('name')->implode(', '),
                'featured_image_url' => $post->featured_image_url,
                'is_live' => $post->isLive(),
            ],
            'defaultAuthor' => $post->author_name,
            'categories' => $this->categories(),
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $wasLive = $post->isLive();
        $this->save($post, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->savedMessage($post, $wasLive)]);

        return to_route('admin.posts.edit', $post);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Post deleted.']);

        return to_route('admin.posts.index');
    }

    /**
     * Store a picture placed inside an article and return its address.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(
            ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120']],
            ['image.max' => 'The image must be 5 MB or smaller.'],
        );

        /** @var UploadedFile $file */
        $file = $request->file('image');

        return response()->json(['url' => '/'.$this->storeImage($file)]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Str::slug((string) $value) === '') {
                    $fail('Give the category a name with letters or numbers in it.');
                }
            }],
        ], ['name.required' => 'Type a name for the new category.'], ['name' => 'category name']);

        $category = PostCategory::firstOrCreate(['slug' => Str::slug($data['name'])], ['name' => trim($data['name'])]);

        // The form picks it straight away.
        Inertia::flash('newCategoryId', $category->id);

        return back();
    }

    private function save(Post $post, PostRequest $request): void
    {
        $data = $request->safe()->except(['tags', 'featured_image_file', 'remove_featured_image']);

        // Only safe HTML is kept, whatever was typed in the HTML view.
        $data['content'] = ArticleHtml::clean($data['content'] ?? '');

        $data['reading_time'] = (int) ($data['reading_time'] ?? 0)
            ?: max(1, (int) ceil(str_word_count(strip_tags($data['content'])) / 200));

        // A post published without a date goes out now.
        if ($data['status'] === PostStatus::Published->value && empty($data['published_at'])) {
            $data['published_at'] = $post->published_at ?? now();
        }

        $file = $request->file('featured_image_file');

        if ($file instanceof UploadedFile) {
            $data['featured_image'] = $this->storeImage($file);
        } elseif ($request->boolean('remove_featured_image')) {
            $data['featured_image'] = null;
        }

        DB::transaction(function () use ($post, $data, $request): void {
            $post->fill($data)->save();
            $post->tags()->sync(Tag::idsFor(explode(',', (string) $request->input('tags'))));
        });
    }

    /**
     * The toast after saving says what happened to the post, not just that it saved.
     */
    private function savedMessage(Post $post, bool $wasLive): string
    {
        $staffOnly = $post->visibility === PostVisibility::Private;

        return match ($post->status) {
            PostStatus::Draft => 'Draft saved. Readers cannot see it yet.',
            PostStatus::Archived => 'Archived. Readers can no longer see it.',
            PostStatus::Scheduled => $post->isLive()
                ? 'Saved. It is on the site.'
                : 'Scheduled for '.$post->published_at?->format('j M Y, g:i a').($staffOnly ? '. Only editors and admins will be able to read it.' : '.'),
            PostStatus::Published => match (true) {
                $staffOnly => 'Saved. Only editors and admins can read it.',
                $wasLive => 'Changes are live.',
                default => 'Published. It is on the site now.',
            },
        };
    }

    /**
     * Save an image and return its path relative to the public folder.
     */
    private function storeImage(UploadedFile $file): string
    {
        $name = 'img_'.Str::lower(Str::random(20)).'.'.$file->extension();
        Storage::disk('media')->putFileAs('blog_images', $file, $name);

        return 'assets/media/blog_images/'.$name;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categories(): array
    {
        return PostCategory::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (PostCategory $c): array => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->all();
    }
}
