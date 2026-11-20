@extends('layouts.site')

@section('title', 'Knowledge Base')
@section('description', 'Research insights, methods and practical guides from the OASIS Research Community.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h1 class="font-display text-5xl font-semibold tracking-tight">Knowledge Base</h1>
        <p class="mt-3 max-w-2xl text-lg text-muted-foreground">Research insights, methods and practical guides from the OASIS Research Community.</p>

        @if ($featured->isNotEmpty())
            <section class="mt-12">
                <h2 class="font-display text-3xl font-semibold">Featured Posts</h2>
                <div class="mt-6 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featured as $post)
                        @include('site.partials.post-card', ['post' => $post])
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-16 grid gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-14">
                @foreach (['Editor’s Choice' => $editorsChoice, 'Popular Posts' => $popular] as $heading => $list)
                    @if ($list->isNotEmpty())
                        <section>
                            <h2 class="font-display text-3xl font-semibold">{{ $heading }}</h2>
                            <div class="mt-6 grid gap-8 sm:grid-cols-2">
                                @foreach ($list as $post)
                                    @include('site.partials.post-card', ['post' => $post])
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                <section>
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <h2 class="font-display text-3xl font-semibold">Most Recent</h2>
                        <a href="{{ route('posts.index') }}" class="font-semibold underline hover:text-brand-crimson-strong">View all posts</a>
                    </div>
                    @if ($recent->isEmpty())
                        <p class="mt-6 text-muted-foreground">No posts have been published yet.</p>
                    @else
                        <div class="mt-6 grid gap-8 sm:grid-cols-2">
                            @foreach ($recent as $post)
                                @include('site.partials.post-card', ['post' => $post])
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            @include('site.partials.sidebar')
        </div>
    </div>
@endsection
