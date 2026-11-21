{{-- One approved comment with its replies underneath, indented along a thin line. --}}
<li id="comment-{{ $comment->id }}">
    <div class="rounded-card bg-brand-paper p-5 sm:p-6">
        <p class="font-bold">{{ $comment->author_name }}</p>
        <p class="text-caption01 text-brand-navy/60"><time datetime="{{ $comment->created_at?->toDateString() }}">{{ $comment->created_at?->format('j M Y') }}</time></p>
        <p class="mt-3 whitespace-pre-line">{{ $comment->content }}</p>
        @if ($post->allow_comments)
            <a href="{{ route('posts.show', ['slug' => $post->slug, 'reply' => $comment->id]) }}#comment-form" class="mt-2 inline-flex min-h-11 items-center font-semibold underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong">Reply</a>
        @endif
    </div>
    @if ($replies->has($comment->id))
        <ul class="mt-4 ml-4 space-y-4 border-l-2 border-brand-line pl-4 sm:ml-6 sm:pl-6">
            @foreach ($replies[$comment->id] as $reply)
                @include('site.partials.comment', ['comment' => $reply])
            @endforeach
        </ul>
    @endif
</li>
