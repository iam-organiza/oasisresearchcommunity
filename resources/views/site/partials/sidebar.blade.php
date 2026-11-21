{{-- Knowledge Base sidebar: search, top categories, recent posts and popular tags; a page can leave out the search ($showSearch) or the recent posts ($showRecent). --}}
<aside class="space-y-12">
    @if ($showSearch ?? true)
        <form action="{{ route('posts.index') }}" method="get" role="search">
            <label for="kb-search" class="font-display text-heading03 font-bold">Search the Knowledge Base</label>
            <div class="mt-3 flex gap-2">
                <input id="kb-search" type="search" name="search" value="{{ $search ?? '' }}" class="min-h-12 w-full min-w-0 rounded-control bg-brand-sheet px-4 ring-1 ring-brand-line focus:ring-2 focus:ring-brand-navy focus:outline-none" placeholder="Sampling, ethics, MEL">
                <button type="submit" class="btn btn-ink">Search</button>
            </div>
        </form>
    @endif

    @if ($categories->isNotEmpty())
        <section>
            @include('site.partials.section-divider', ['label' => 'Top Categories', 'tag' => 'h2', 'tight' => true])
            <ul class="-mt-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('posts.index', ['category' => $category->slug]) }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-brand-line font-semibold capitalize hover:text-brand-crimson-strong">
                            <span>{{ $category->name }}</span>
                            <span class="text-caption01 font-normal text-brand-navy/60 tabular-nums">{{ $category->posts_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (($showRecent ?? true) && $recentPosts->isNotEmpty())
        <section>
            @include('site.partials.section-divider', ['label' => 'Recent Posts', 'tag' => 'h2', 'tight' => true])
            <div class="space-y-5">
                @foreach ($recentPosts as $recent)
                    @include('site.partials.post-card', ['post' => $recent, 'compact' => true])
                @endforeach
            </div>
        </section>
    @endif

    @if ($popularTags->isNotEmpty())
        <section>
            @include('site.partials.section-divider', ['label' => 'Popular Tags', 'tag' => 'h2', 'tight' => true])
            <ul class="flex flex-wrap gap-2">
                @foreach ($popularTags as $tag)
                    <li><a href="{{ route('posts.index', ['tag' => $tag->slug]) }}" class="inline-flex min-h-9 items-center rounded-control bg-brand-paper px-3 text-caption01 font-semibold transition-colors hover:bg-brand-navy hover:text-white">{{ $tag->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</aside>
