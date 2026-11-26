<x-mail::message>
# Hello {{ $user->first_name }},

Here is your {{ $month }} update from OASIS Research Community.

<x-mail::panel>
**Your membership**

{{ $membership->grade->label() }} · {{ $membership->number ?? 'number issued when your induction fee is paid' }} · {{ $membership->statusLabel() }}

@if ($membership->activated_at === null)
Your membership becomes active once your induction fee is paid.
@elseif ($membership->isLapsed())
Your membership has lapsed because a subscription is unpaid. Your portfolio page is offline until you pay; everything comes back as soon as you do.
@elseif ($next && $next->balance() > 0 && $next->due_on && $next->due_on->lte(today()))
Your subscription of ₦{{ number_format($next->balance()) }} was due on {{ $next->due_on->format('j F Y') }}. Please pay by {{ $next->graceEndsOn()?->format('j F Y') }} to keep your membership active.
@elseif ($next && $next->balance() > 0)
Your next subscription, ₦{{ number_format($next->balance()) }}, is due on {{ $next->due_on?->format('j F Y') }}. You can pay early, all at once or in parts.
@elseif ($next)
Your subscription is paid. Thank you.
@endif
@if ($owed > 0 && $owed !== ($next?->balance() ?? 0))

You have ₦{{ number_format($owed) }} to pay in all.
@endif
</x-mail::panel>

<x-mail::button :url="route('membership.account')">
View my fees and payments
</x-mail::button>

@if ($posts->isNotEmpty())
## New in the Knowledge Base

@foreach ($posts as $post)
@if ($post->excerpt)
**[{{ $post->title }}]({{ route('posts.show', $post->slug) }})**\
{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 140) }}
@else
**[{{ $post->title }}]({{ route('posts.show', $post->slug) }})**
@endif

@endforeach
@endif

<x-slot:subcopy>
You get this email once a month as a member. [Stop these monthly emails]({{ $unsubscribeUrl }}). You will still get receipts and reminders about your subscription.
</x-slot:subcopy>
</x-mail::message>
