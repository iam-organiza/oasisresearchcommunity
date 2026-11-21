@props(['post', 'compact' => false])
{{-- A post in a list: image, category, title, summary and date. The compact version is a small row for sidebars and lists. --}}
<article @class(['group flex', 'gap-4' => $compact, 'flex-col' => ! $compact])>
    <a href="{{ route('posts.show', $post->slug) }}" @class(['w-20 shrink-0' => $compact]) tabindex="-1" aria-hidden="true">
        @if ($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}" alt="" loading="lazy" @class(['w-full bg-brand-paper object-cover', 'aspect-square rounded-control' => $compact, 'aspect-16/10 rounded-card' => ! $compact])>
        @else
            <div @class(['w-full bg-brand-navy', 'aspect-square rounded-control' => $compact, 'aspect-16/10 rounded-card' => ! $compact])></div>
        @endif
    </a>
    <div @class(['min-w-0' => $compact, 'mt-4' => ! $compact])>
        @if ($post->category)
            <a href="{{ route('posts.index', ['category' => $post->category->slug]) }}" class="text-caption01 font-semibold text-brand-crimson-strong capitalize hover:underline">{{ $post->category->name }}</a>
        @endif
        <h3 @class(['font-display font-bold', 'text-base leading-snug' => $compact, 'mt-1 text-heading03' => ! $compact])>
            <a href="{{ route('posts.show', $post->slug) }}" class="hover:text-brand-crimson-strong">{{ $post->title }}</a>
        </h3>
        @unless ($compact)
            <p class="mt-2 line-clamp-2 text-brand-navy/75">{{ $post->summary }}</p>
        @endunless
        <p class="mt-2 text-caption01 text-brand-navy/60">
            <time datetime="{{ $post->displayDate()?->toDateString() }}">{{ $post->displayDate()?->format('j M Y') }}</time>@if ($post->reading_time), {{ $post->reading_time }} min read @endif
        </p>
    </div>
</article>
