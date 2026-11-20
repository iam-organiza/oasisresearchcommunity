<li id="comment-{{ $comment->id }}" class="scroll-mt-6">
    <div class="rounded-2xl bg-brand-paper p-5">
        <p class="font-semibold">{{ $comment->author_name }}</p>
        <p class="text-sm text-muted-foreground">{{ $comment->created_at?->format('j M Y') }}</p>
        <p class="mt-3 whitespace-pre-line">{{ $comment->content }}</p>
        @if ($post->allow_comments)
            <a href="{{ route('posts.show', ['slug' => $post->slug, 'reply' => $comment->id]) }}#comment-form" class="mt-3 inline-flex min-h-11 items-center font-semibold underline hover:text-brand-crimson-strong">Reply</a>
        @endif
    </div>
    @if ($replies->has($comment->id))
        <ul class="mt-4 ml-5 space-y-4 border-l-2 border-border pl-5 sm:ml-8">
            @foreach ($replies[$comment->id] as $reply)
                @include('site.partials.comment', ['comment' => $reply])
            @endforeach
        </ul>
    @endif
</li>
