@props(['post', 'compact' => false])
<article class="flex {{ $compact ? 'gap-4' : 'flex-col' }}">
    <a href="{{ route('posts.show', $post->slug) }}" class="{{ $compact ? 'w-24 shrink-0' : '' }}" tabindex="-1" aria-hidden="true">
        @if ($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}" alt="" loading="lazy" class="{{ $compact ? 'aspect-square rounded-xl' : 'aspect-[16/10] rounded-2xl' }} w-full bg-brand-paper object-cover">
        @else
            <div class="{{ $compact ? 'aspect-square rounded-xl' : 'aspect-[16/10] rounded-2xl' }} w-full bg-brand-navy"></div>
        @endif
    </a>
    <div class="{{ $compact ? 'min-w-0' : 'mt-4' }}">
        @if ($post->category)
            <a href="{{ route('posts.index', ['category' => $post->category->slug]) }}" class="text-sm font-semibold text-brand-crimson-strong capitalize hover:underline">{{ $post->category->name }}</a>
        @endif
        <h3 class="font-display {{ $compact ? 'text-lg' : 'mt-1 text-2xl' }} leading-snug font-semibold">
            <a href="{{ route('posts.show', $post->slug) }}" class="hover:text-brand-crimson-strong">{{ $post->title }}</a>
        </h3>
        @unless ($compact)
            <p class="mt-2 line-clamp-3 text-muted-foreground">{{ $post->summary }}</p>
        @endunless
        <p class="mt-2 text-sm text-muted-foreground">
            <time datetime="{{ $post->displayDate()?->toDateString() }}">{{ $post->displayDate()?->format('j M Y') }}</time>@if ($post->reading_time), {{ $post->reading_time }} min read @endif
        </p>
    </div>
</article>
