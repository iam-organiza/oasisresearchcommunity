@php
    // What the status card says about money, in order: not yet active, lapsed, overdue, coming up, paid.
    $naira = fn (int $amount) => '₦'.number_format($amount);
    $money = match (true) {
        $membership->activated_at === null => [
            'title' => 'Induction fee to pay',
            'amount' => $dueNow,
            'note' => 'Your membership becomes active, with its number, once your induction fee is paid.',
        ],
        $membership->isLapsed() => [
            'title' => 'Subscription unpaid',
            'amount' => $dueNow,
            'note' => 'Your membership has lapsed because a subscription is unpaid. Your portfolio page is offline until you pay; everything comes back as soon as you do.',
        ],
        $next && $next->balance() > 0 && $next->due_on && $next->due_on->lte(today()) => [
            'title' => 'Subscription due',
            'amount' => $next->balance(),
            'note' => 'Due on '.$next->due_on->format('j F Y').'. Please pay by '.$next->graceEndsOn()?->format('j F Y').' to keep your membership active.',
        ],
        $next && $next->balance() > 0 => [
            'title' => 'Next subscription',
            'amount' => $next->balance(),
            'note' => 'Due on '.$next->due_on?->format('j F Y').'. You can pay early, all at once or in parts.',
        ],
        default => null,
    };
    $shown = $money['amount'] ?? 0;
@endphp
<x-mail::message>
# Hello {{ $user->first_name }},

Here is your {{ $month }} update from OASIS Research Community.

<table class="status-card" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="status-cell" width="30%"><p class="status-label">Grade</p><p class="status-value">{{ $membership->grade->label() }}</p></td>
<td class="status-cell" width="42%"><p class="status-label">Number</p><p class="status-value">{{ $membership->number ?? 'After your induction fee' }}</p></td>
<td class="status-cell" width="28%"><p class="status-label">Status</p><p class="status-value">{{ $membership->statusLabel() }}</p></td>
</tr>
<tr>
<td class="status-money" colspan="3">
@if ($money)
<p class="status-label">{{ $money['title'] }}</p>
<p class="status-amount">{{ $naira($money['amount']) }}</p>
<p class="status-note">{{ $money['note'] }}</p>
@else
<p class="status-note">Your subscription is paid. Thank you.</p>
@endif
@if ($owed > 0 && $owed !== $shown)
<p class="status-note">You have {{ $naira($owed) }} to pay in all.</p>
@endif
</td>
</tr>
</table>

<x-mail::button :url="route('membership.account')">
{{ $dueNow > 0 ? 'Pay '.$naira($dueNow) : 'View my fees and payments' }}
</x-mail::button>

@if ($posts->isNotEmpty())
## New in the Knowledge Base

<table class="post-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($posts as $post)
<tr>
<td class="post-item">
<p class="post-title"><a href="{{ route('posts.show', $post->slug) }}">{{ $post->title }}</a></p>
@if ($post->excerpt)
<p class="post-excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 140) }}</p>
@endif
<p class="post-read"><a href="{{ route('posts.show', $post->slug) }}">Read</a></p>
</td>
</tr>
@endforeach
</table>
@endif

<x-slot:subcopy>
You get this email once a month as a member. [Stop these monthly emails]({{ $unsubscribeUrl }}). You will still get receipts and reminders about your subscription.
</x-slot:subcopy>
</x-mail::message>
