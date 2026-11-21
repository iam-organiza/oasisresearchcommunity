{{-- Page numbers for a list of posts: Previous, the numbered pages (the current one in navy) and Next. --}}
@if ($paginator->hasPages())
    <nav aria-label="Pages" class="flex flex-wrap items-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="btn btn-quiet min-h-11 opacity-40" aria-disabled="true">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-quiet min-h-11">Previous</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-brand-navy/60">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="btn btn-ink min-h-11 min-w-11 px-3">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="Page {{ $page }}" class="btn btn-quiet min-h-11 min-w-11 px-3">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-quiet min-h-11">Next</a>
        @else
            <span class="btn btn-quiet min-h-11 opacity-40" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
