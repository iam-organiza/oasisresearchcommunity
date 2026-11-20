<aside class="space-y-10">
    <form action="{{ route('posts.index') }}" method="get" role="search">
        <label for="kb-search" class="font-display text-xl font-semibold">Search the Knowledge Base</label>
        <div class="mt-3 flex gap-2">
            <input id="kb-search" type="search" name="search" value="{{ $search ?? '' }}" class="min-h-11 w-full min-w-0 rounded-full border border-input px-4" placeholder="Sampling, ethics, MEL">
            <button type="submit" class="min-h-11 rounded-full bg-brand-navy px-5 font-semibold text-white hover:bg-brand-crimson-strong">Search</button>
        </div>
    </form>

    @if ($categories->isNotEmpty())
        <section>
            <h2 class="font-display text-xl font-semibold">Top Categories</h2>
            <ul class="mt-3 divide-y divide-border">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('posts.index', ['category' => $category->slug]) }}" class="flex min-h-11 items-center justify-between gap-3 capitalize hover:text-brand-crimson-strong">
                            <span>{{ $category->name }}</span>
                            <span class="text-sm text-muted-foreground">{{ $category->posts_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($recentPosts->isNotEmpty())
        <section>
            <h2 class="font-display text-xl font-semibold">Recent Posts</h2>
            <div class="mt-4 space-y-5">
                @foreach ($recentPosts as $recent)
                    @include('site.partials.post-card', ['post' => $recent, 'compact' => true])
                @endforeach
            </div>
        </section>
    @endif

    @if ($popularTags->isNotEmpty())
        <section>
            <h2 class="font-display text-xl font-semibold">Popular Tags</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($popularTags as $tag)
                    <li><a href="{{ route('posts.index', ['tag' => $tag->slug]) }}" class="inline-flex min-h-9 items-center rounded-full border border-border px-3 text-sm hover:border-brand-navy">{{ $tag->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</aside>
