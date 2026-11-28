@extends('layouts.site')

@section('title', $post->meta_title ?: $post->title)
@section('description', $post->meta_description ?: $post->summary)
@section('og_type', 'article')
@section('og_title', $post->og_title ?: $post->title)
@section('og_description', $post->og_description ?: ($post->meta_description ?: $post->summary))
@section('canonical', $post->canonical_url ?: route('posts.show', $post->slug))
@if (\App\Models\Post::mediaUrl($post->og_image ?: $post->featured_image))
    @section('image', url(\App\Models\Post::mediaUrl($post->og_image ?: $post->featured_image)))
@endif

@push('head')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->summary,
            'datePublished' => $post->displayDate()?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => ['@type' => 'Person', 'name' => $post->author_name],
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name')],
            'image' => $post->featured_image_url ? url($post->featured_image_url) : null,
            'mainEntityOfPage' => route('posts.show', $post->slug),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
    {{--
        Post page: a pale header band (breadcrumb, category, title, byline), the image straddling the band's edge, then
        the article, tags and comments in one centred column at a comfortable reading width, and a Keep reading band
        at the end with recent posts, search, categories and tags.
    --}}
    <section @class(['pt-section bg-brand-paper px-page', 'pb-40 sm:pb-56 lg:pb-72' => $post->featured_image_url, 'pb-16' => ! $post->featured_image_url])>
        <div class="mx-auto max-w-3xl">
            <nav aria-label="Breadcrumb" class="text-caption01 font-semibold">
                <a href="{{ route('knowledge-base') }}" class="text-brand-navy/70 underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong">Knowledge Base</a>
                <span class="text-brand-navy/40" aria-hidden="true">/</span>
                <a href="{{ route('posts.index') }}" class="text-brand-navy/70 underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong">Posts</a>
            </nav>

            @unless ($post->isLive())
                <p class="mt-6 rounded-control bg-brand-sheet px-4 py-3 font-semibold ring-2 ring-brand-crimson">@if ($post->visibility === \App\Enums\PostVisibility::Private)
                    Only editors and admins can see this post. Readers never see it.
                @elseif ($post->status === \App\Enums\PostStatus::Scheduled)
                    Only editors and admins can see this post until {{ $post->published_at?->format('j M Y, g:i a') }}, when it goes out.
                @else
                    Only editors and admins can see this post. {{ $post->status === \App\Enums\PostStatus::Draft ? 'It is a draft' : 'It is archived' }}, so readers cannot see it.
                @endif</p>
            @endunless

            @if ($post->category)
                <a href="{{ route('posts.index', ['category' => $post->category->slug]) }}" class="mt-10 inline-block font-semibold text-brand-crimson-strong capitalize hover:underline">{{ $post->category->name }}</a>
            @endif
            <h1 @class(['font-display text-heading01 font-black text-balance', 'mt-3' => $post->category, 'mt-10' => ! $post->category])>{{ $post->title }}</h1>
            <p class="mt-6 text-body01 text-brand-navy/75">
                By <span class="font-semibold text-brand-navy">{{ $post->author_name }}</span>,
                <time datetime="{{ $post->displayDate()?->toDateString() }}">{{ $post->displayDate()?->format('j F Y') }}</time>@if ($post->reading_time), {{ $post->reading_time }} min read @endif
            </p>
        </div>
    </section>

    <div class="page-section bg-brand-sheet px-page">
        @if ($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}" alt="" class="mx-auto -mt-32 aspect-16/9 w-full max-w-6xl rounded-card bg-brand-desk object-cover sm:-mt-48 lg:-mt-64">
        @endif

        <div @class(['mx-auto max-w-3xl', 'mt-14 lg:mt-20' => $post->featured_image_url, 'pt-14' => ! $post->featured_image_url])>
            <article>
                {{-- Written by staff in the post editor, so it is shown as formatted HTML. --}}
                <div class="article-body">{{-- Cleaned again on the way out, so articles saved before cleaning existed are safe too. --}}{!! \App\Support\ArticleHtml::clean($post->content) !!}</div>

                @if ($post->tags->isNotEmpty())
                    <ul class="mt-12 flex flex-wrap items-center gap-2 border-t border-brand-line pt-6" aria-label="Tags">
                        @foreach ($post->tags as $tag)
                            <li><a href="{{ route('posts.index', ['tag' => $tag->slug]) }}" class="inline-flex min-h-9 items-center rounded-control bg-brand-paper px-3 text-caption01 font-semibold transition-colors hover:bg-brand-navy hover:text-white">{{ $tag->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </article>

            <section id="comments" class="mt-20">
                @include('site.partials.section-divider', ['label' => $commentCount.' '.Str::plural('Comment', $commentCount), 'tag' => 'h2', 'tight' => true])

                @if ($comments->isNotEmpty())
                    <ul class="space-y-4">
                        @foreach ($comments as $comment)
                            @include('site.partials.comment', ['comment' => $comment])
                        @endforeach
                    </ul>
                @endif

                @if (session('comment_sent'))
                    <p role="status" class="mt-6 rounded-control bg-brand-paper px-4 py-3 font-semibold">Comment sent. It will appear here once an editor has approved it.</p>
                @endif

                @if ($post->allow_comments && $post->isLive())
                    <form id="comment-form" method="post" action="{{ route('posts.comments.store', $post->slug) }}" class="mt-12 space-y-5 rounded-card p-6 ring-1 ring-brand-line sm:p-8">
                        @csrf
                        <h3 class="font-display text-heading02 font-black">Leave a reply</h3>

                        @if ($replyTo)
                            <p class="rounded-control bg-brand-paper px-4 py-3">
                                Replying to {{ $replyTo->author_name }}.
                                <a href="{{ route('posts.show', $post->slug) }}#comment-form" class="font-semibold underline">Cancel reply</a>
                            </p>
                            <input type="hidden" name="parent_id" value="{{ $replyTo->id }}">
                        @endif

                        <div class="hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="author_name" class="font-semibold">Name</label>
                                <input id="author_name" name="author_name" type="text" required maxlength="255" autocomplete="name" value="{{ old('author_name') }}" class="mt-2 min-h-12 w-full rounded-control bg-brand-sheet px-4 ring-1 ring-brand-line focus:ring-2 focus:ring-brand-navy focus:outline-none">
                                @error('author_name') <p class="mt-1 text-caption01 font-semibold text-brand-crimson-strong">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="author_email" class="font-semibold">Email <span class="font-normal text-brand-navy/60">(not shown)</span></label>
                                <input id="author_email" name="author_email" type="email" required maxlength="255" autocomplete="email" value="{{ old('author_email') }}" class="mt-2 min-h-12 w-full rounded-control bg-brand-sheet px-4 ring-1 ring-brand-line focus:ring-2 focus:ring-brand-navy focus:outline-none">
                                @error('author_email') <p class="mt-1 text-caption01 font-semibold text-brand-crimson-strong">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label for="content" class="font-semibold">Comment</label>
                            <textarea id="content" name="content" rows="5" required maxlength="5000" class="mt-2 min-h-12 w-full rounded-control bg-brand-sheet px-4 ring-1 ring-brand-line focus:ring-2 focus:ring-brand-navy focus:outline-none py-3">{{ old('content') }}</textarea>
                            @error('content') <p class="mt-1 text-caption01 font-semibold text-brand-crimson-strong">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="btn btn-ink">Send comment</button>
                    </form>
                @elseif (! $post->allow_comments)
                    <p class="mt-6 text-brand-navy/75">Comments are closed on this post.</p>
                @endif
            </section>
        </div>
    </div>

    {{-- Keep reading: recent posts beside the search, categories and tags. --}}
    <section class="page-section pt-section bg-brand-paper px-page">
        @include('site.partials.section-divider', ['label' => 'Keep reading', 'tag' => 'h2'])
        <div class="grid gap-x-16 gap-y-14 lg:grid-cols-12">
            @if ($recentPosts->isNotEmpty())
                <div class="lg:col-span-7">
                    <h3 class="mb-6 font-display text-heading03 font-bold">Recent Posts</h3>
                    <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        @foreach ($recentPosts as $recent)
                            @include('site.partials.post-card', ['post' => $recent, 'compact' => true])
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="lg:col-span-5">
                @include('site.partials.sidebar', ['showRecent' => false])
            </div>
        </div>
    </section>
@endsection
