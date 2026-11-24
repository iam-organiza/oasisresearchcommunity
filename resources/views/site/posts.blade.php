@extends('layouts.site')

@php
    $heading = match (true) {
        $activeCategory !== null => 'Posts in '.ucfirst($activeCategory->name),
        $activeTag !== null => 'Posts tagged '.$activeTag->name,
        $search !== '' => 'Results for “'.$search.'”',
        default => 'All posts',
    };
    $filtered = $activeCategory || $activeTag || $search !== '';
@endphp

@section('title', $heading)
@section('description', 'Latest research insights and articles from the OASIS Research Community.')

@section('content')
    {{-- All posts: a pale filter bar (heading, count, search, categories), then the posts in three columns with page numbers. --}}
    <section class="page-section pt-section bg-brand-paper px-page">
        <nav aria-label="Breadcrumb" class="text-caption01 font-semibold">
            <a href="{{ route('knowledge-base') }}" class="text-brand-navy/70 underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong">Knowledge Base</a>
            <span class="text-brand-navy/40" aria-hidden="true">/</span>
            <span aria-current="page">Posts</span>
        </nav>

        <div class="mt-6 grid items-end gap-x-16 gap-y-10 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <h1 class="font-display text-hero02 font-black text-balance">{{ $heading }}</h1>
                <p class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-body01 text-brand-navy/75">
                    <span>{{ $posts->total() }} {{ Str::plural('post', $posts->total()) }}</span>
                    @if ($filtered)
                        <a href="{{ route('posts.index') }}" class="font-semibold text-brand-navy underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong">Show all posts</a>
                    @endif
                </p>
            </div>
            <form action="{{ route('posts.index') }}" method="get" role="search" class="lg:col-span-5">
                <label for="kb-search" class="text-caption01 font-semibold">Search the Knowledge Base</label>
                <div class="mt-2 flex gap-2">
                    <input id="kb-search" type="search" name="search" value="{{ $search }}" class="min-h-14 w-full min-w-0 rounded-control bg-brand-sheet px-5 text-body01 ring-1 ring-brand-line focus:ring-2 focus:ring-brand-navy focus:outline-none" placeholder="Sampling, ethics, MEL">
                    <button type="submit" class="btn btn-ink min-h-14 px-7">Search</button>
                </div>
            </form>
        </div>

        @if ($categories->isNotEmpty())
            <nav aria-label="Categories" class="mt-12 flex flex-wrap gap-2 lg:mt-14">
                <a href="{{ route('posts.index') }}" @if (! $activeCategory) aria-current="page" @endif @class([
                    'inline-flex min-h-11 items-center rounded-control px-4 font-semibold transition-colors',
                    'bg-brand-navy text-white' => ! $activeCategory,
                    'bg-brand-sheet ring-1 ring-brand-line hover:bg-brand-navy hover:text-white hover:ring-brand-navy' => $activeCategory,
                ])>All categories</a>
                @foreach ($categories as $category)
                    @php($on = $activeCategory?->is($category))
                    <a href="{{ route('posts.index', ['category' => $category->slug]) }}" @if ($on) aria-current="page" @endif @class([
                        'inline-flex min-h-11 items-center gap-2 rounded-control px-4 font-semibold capitalize transition-colors',
                        'bg-brand-navy text-white' => $on,
                        'bg-brand-sheet ring-1 ring-brand-line hover:bg-brand-navy hover:text-white hover:ring-brand-navy' => ! $on,
                    ])>
                        {{ $category->name }}
                        <span class="text-caption01 font-normal opacity-60 tabular-nums">{{ $category->posts_count }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </section>

    <div class="page-section pt-section bg-brand-sheet px-page">
        @if ($posts->isEmpty())
            <div class="max-w-2xl">
                <p class="font-display text-heading02 font-bold">No posts match.</p>
                <p class="mt-3 text-body01 text-brand-navy/75">Try a different search word, or choose a category above.</p>
                <a href="{{ route('posts.index') }}" class="btn btn-ink mt-8">See all posts</a>
            </div>
        @else
            <div class="grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('site.partials.post-card', ['post' => $post])
                @endforeach
            </div>
            <div class="mt-16">{{ $posts->links('site.partials.pagination') }}</div>
        @endif

        @if ($recentPosts->isNotEmpty() || $popularTags->isNotEmpty())
            <div class="mt-20 grid gap-x-16 gap-y-14 lg:mt-28 lg:grid-cols-12">
                @if ($recentPosts->isNotEmpty())
                    <section class="lg:col-span-7">
                        @include('site.partials.section-divider', ['label' => 'Recent Posts', 'tag' => 'h2', 'tight' => true])
                        <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                            @foreach ($recentPosts as $recent)
                                @include('site.partials.post-card', ['post' => $recent, 'compact' => true])
                            @endforeach
                        </div>
                    </section>
                @endif
                @if ($popularTags->isNotEmpty())
                    <section class="lg:col-span-5">
                        @include('site.partials.section-divider', ['label' => 'Popular Tags', 'tag' => 'h2', 'tight' => true])
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($popularTags as $tag)
                                @php($on = $activeTag?->is($tag))
                                <li><a href="{{ route('posts.index', ['tag' => $tag->slug]) }}" @if ($on) aria-current="page" @endif @class([
                                    'inline-flex min-h-9 items-center rounded-control px-3 text-caption01 font-semibold transition-colors',
                                    'bg-brand-navy text-white' => $on,
                                    'bg-brand-paper hover:bg-brand-navy hover:text-white' => ! $on,
                                ])>{{ $tag->name }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        @endif
    </div>
@endsection
