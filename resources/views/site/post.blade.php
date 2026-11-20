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
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-muted-foreground">
            <a href="{{ route('knowledge-base') }}" class="underline hover:text-brand-crimson-strong">Knowledge Base</a> /
            <a href="{{ route('posts.index') }}" class="underline hover:text-brand-crimson-strong">Posts</a>
        </nav>

        @unless ($post->isLive())
            <p class="mt-4 rounded-xl border border-brand-crimson bg-white px-4 py-3 font-medium">Only staff can see this post. Its status is {{ $post->status->label() }} and its visibility is {{ $post->visibility->value }}.</p>
        @endunless

        <div class="mt-6 grid gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0">
                <article>
                    @if ($post->category)
                        <a href="{{ route('posts.index', ['category' => $post->category->slug]) }}" class="font-semibold text-brand-crimson-strong capitalize hover:underline">{{ $post->category->name }}</a>
                    @endif
                    <h1 class="mt-2 font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">{{ $post->title }}</h1>
                    <p class="mt-4 text-muted-foreground">
                        By {{ $post->author_name }},
                        <time datetime="{{ $post->displayDate()?->toDateString() }}">{{ $post->displayDate()?->format('j F Y') }}</time>@if ($post->reading_time), {{ $post->reading_time }} min read @endif
                    </p>

                    @if ($post->featured_image_url)
                        <img src="{{ $post->featured_image_url }}" alt="" class="mt-8 aspect-[16/9] w-full rounded-2xl bg-brand-paper object-cover">
                    @endif

                    {{-- Written by staff in the post editor, so it is shown as formatted HTML. --}}
                    <div class="article-body mt-8">{!! $post->content !!}</div>

                    @if ($post->tags->isNotEmpty())
                        <ul class="mt-10 flex flex-wrap items-center gap-2" aria-label="Tags">
                            @foreach ($post->tags as $tag)
                                <li><a href="{{ route('posts.index', ['tag' => $tag->slug]) }}" class="inline-flex min-h-9 items-center rounded-full border border-border px-3 text-sm hover:border-brand-navy">{{ $tag->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </article>

                <section id="comments" class="mt-16 scroll-mt-6">
                    <h2 class="font-display text-3xl font-semibold">{{ $commentCount }} {{ Str::plural('Comment', $commentCount) }}</h2>

                    @if ($comments->isNotEmpty())
                        <ul class="mt-6 space-y-4">
                            @foreach ($comments as $comment)
                                @include('site.partials.comment', ['comment' => $comment])
                            @endforeach
                        </ul>
                    @endif

                    @if (session('comment_sent'))
                        <p role="status" class="mt-6 rounded-xl bg-brand-paper px-4 py-3 font-medium">Comment sent. It will appear here once an editor has approved it.</p>
                    @endif

                    @if ($post->allow_comments && $post->isLive())
                        <form id="comment-form" method="post" action="{{ route('posts.comments.store', $post->slug) }}" class="mt-10 scroll-mt-6 space-y-5">
                            @csrf
                            <h3 class="font-display text-2xl font-semibold">Leave a reply</h3>

                            @if ($replyTo)
                                <p class="rounded-xl bg-brand-paper px-4 py-3">
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
                                    <label for="author_name" class="font-medium">Name</label>
                                    <input id="author_name" name="author_name" type="text" required maxlength="255" autocomplete="name" value="{{ old('author_name') }}" class="mt-1 min-h-11 w-full rounded-lg border border-input px-3">
                                    @error('author_name') <p class="mt-1 text-sm text-brand-crimson-strong">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="author_email" class="font-medium">Email <span class="font-normal text-muted-foreground">(not shown)</span></label>
                                    <input id="author_email" name="author_email" type="email" required maxlength="255" autocomplete="email" value="{{ old('author_email') }}" class="mt-1 min-h-11 w-full rounded-lg border border-input px-3">
                                    @error('author_email') <p class="mt-1 text-sm text-brand-crimson-strong">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label for="content" class="font-medium">Comment</label>
                                <textarea id="content" name="content" rows="5" required maxlength="5000" class="mt-1 w-full rounded-lg border border-input px-3 py-2">{{ old('content') }}</textarea>
                                @error('content') <p class="mt-1 text-sm text-brand-crimson-strong">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="min-h-12 rounded-full bg-brand-navy px-7 font-semibold text-white hover:bg-brand-crimson-strong">Send comment</button>
                        </form>
                    @elseif (! $post->allow_comments)
                        <p class="mt-6 text-muted-foreground">Comments are closed on this post.</p>
                    @endif
                </section>
            </div>

            @include('site.partials.sidebar')
        </div>
    </div>
@endsection
