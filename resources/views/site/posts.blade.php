@extends('layouts.site')

@php
    $heading = match (true) {
        $activeCategory !== null => 'Posts in '.ucfirst($activeCategory->name),
        $activeTag !== null => 'Posts tagged '.$activeTag->name,
        $search !== '' => 'Results for “'.$search.'”',
        default => 'All posts',
    };
@endphp

@section('title', $heading)
@section('description', 'Latest research insights and articles from the OASIS Research Community.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-muted-foreground">
            <a href="{{ route('knowledge-base') }}" class="underline hover:text-brand-crimson-strong">Knowledge Base</a> / Posts
        </nav>
        <h1 class="mt-3 font-display text-5xl font-semibold tracking-tight">{{ $heading }}</h1>
        @if ($activeCategory || $activeTag || $search !== '')
            <a href="{{ route('posts.index') }}" class="mt-3 inline-block font-semibold underline hover:text-brand-crimson-strong">Show all posts</a>
        @endif

        <div class="mt-10 grid gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                @if ($posts->isEmpty())
                    <p class="text-lg text-muted-foreground">No posts match. Try a different search word, or <a href="{{ route('posts.index') }}" class="underline">see all posts</a>.</p>
                @else
                    <div class="grid gap-x-8 gap-y-12 sm:grid-cols-2">
                        @foreach ($posts as $post)
                            @include('site.partials.post-card', ['post' => $post])
                        @endforeach
                    </div>
                    <div class="mt-12">{{ $posts->links() }}</div>
                @endif
            </div>

            @include('site.partials.sidebar')
        </div>
    </div>
@endsection
