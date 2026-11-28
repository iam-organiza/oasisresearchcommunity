@extends('layouts.site')

{{-- Search title, description and introduction, from Website → Knowledge Base page. --}}
@section('full_title', $kb['knowledge-base.search']['title'])
@section('description', $kb['knowledge-base.search']['description'])

@section('content')
    {{-- Knowledge Base, set like a journal's contents page: title and search, the lead story, then the lists with a slim sidebar. --}}
    <section class="page-section pt-section bg-brand-paper px-page">
        <div class="grid items-end gap-x-16 gap-y-10 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <h1 class="font-display text-hero02 font-black">Knowledge Base</h1>
                <p class="mt-6 max-w-2xl text-body01 text-brand-navy/75">{{ $kb['knowledge-base.intro']['intro'] }}</p>
            </div>
            <form action="{{ route('posts.index') }}" method="get" role="search" class="lg:col-span-5">
                <label for="kb-search" class="text-caption01 font-semibold">Search the Knowledge Base</label>
                <div class="mt-2 flex gap-2">
                    <input id="kb-search" type="search" name="search" class="min-h-14 w-full min-w-0 rounded-control bg-brand-sheet px-5 text-body01 ring-1 ring-brand-line focus:ring-2 focus:ring-brand-navy focus:outline-none" placeholder="Sampling, ethics, MEL">
                    <button type="submit" class="btn btn-ink min-h-14 px-7">Search</button>
                </div>
            </form>
        </div>

        @if ($featured->isNotEmpty())
            @php($lead = $featured->first())
            <div class="mt-16 grid gap-x-16 gap-y-10 lg:mt-20 lg:grid-cols-12">
                {{-- The lead story: the newest featured post, large. --}}
                <article class="lg:col-span-7">
                    <a href="{{ route('posts.show', $lead->slug) }}" tabindex="-1" aria-hidden="true">
                        @if ($lead->featured_image_url)
                            <img src="{{ $lead->featured_image_url }}" alt="" class="aspect-16/10 w-full rounded-card bg-brand-desk object-cover">
                        @else
                            <div class="aspect-16/10 w-full rounded-card bg-brand-navy"></div>
                        @endif
                    </a>
                    <p class="mt-6 text-caption01 font-semibold">
                        <span class="text-brand-navy/60">Featured</span>
                        @if ($lead->category)
                            <span class="text-brand-navy/30" aria-hidden="true">/</span>
                            <a href="{{ route('posts.index', ['category' => $lead->category->slug]) }}" class="text-brand-crimson-strong capitalize hover:underline">{{ $lead->category->name }}</a>
                        @endif
                    </p>
                    <h2 class="mt-2 font-display text-heading01 font-black text-balance">
                        <a href="{{ route('posts.show', $lead->slug) }}" class="hover:text-brand-crimson-strong">{{ $lead->title }}</a>
                    </h2>
                    <p class="mt-4 max-w-2xl text-body01 text-brand-navy/75">{{ $lead->summary }}</p>
                    <p class="mt-4 text-caption01 text-brand-navy/60">
                        <time datetime="{{ $lead->displayDate()?->toDateString() }}">{{ $lead->displayDate()?->format('j M Y') }}</time>@if ($lead->reading_time), {{ $lead->reading_time }} min read @endif
                    </p>
                </article>

                @if ($featured->count() > 1)
                    <div class="lg:col-span-5">
                        @include('site.partials.section-divider', ['label' => 'More Featured Posts', 'tag' => 'h2', 'tight' => true])
                        <div class="space-y-6">
                            @foreach ($featured->skip(1) as $post)
                                @include('site.partials.post-card', ['post' => $post, 'compact' => true])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>

    <div class="page-section pt-section bg-brand-sheet px-page">
        <div class="grid gap-x-16 gap-y-16 lg:grid-cols-12">
            <div class="space-y-16 lg:col-span-8">
                @foreach (['Editor’s Choice' => $editorsChoice, 'Popular Posts' => $popular] as $heading => $list)
                    @if ($list->isNotEmpty())
                        <section>
                            @include('site.partials.section-divider', ['label' => $heading, 'tag' => 'h2', 'tight' => true])
                            <div class="grid gap-x-8 gap-y-12 sm:grid-cols-2">
                                @foreach ($list as $post)
                                    @include('site.partials.post-card', ['post' => $post])
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                <section>
                    @include('site.partials.section-divider', ['label' => 'Most Recent', 'tag' => 'h2', 'tight' => true])
                    @if ($recent->isEmpty())
                        <p class="text-body01 text-brand-navy/75">No posts have been published yet.</p>
                    @else
                        <div class="grid gap-x-8 gap-y-12 sm:grid-cols-2">
                            @foreach ($recent as $post)
                                @include('site.partials.post-card', ['post' => $post])
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('posts.index') }}" class="btn btn-quiet mt-12">View all posts</a>
                </section>
            </div>

            <div class="lg:col-span-4">
                @include('site.partials.sidebar', ['showSearch' => false])
            </div>
        </div>
    </div>
@endsection
