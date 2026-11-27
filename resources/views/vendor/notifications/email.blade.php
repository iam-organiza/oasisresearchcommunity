{{--
    Every membership email. Besides Laravel's lines and action, a notification can pass (with ->markdown('notifications::email', [...])):
    $facts: [[label, value], ...] for a fact card; $quote: ['label' => ..., 'text' => ...], or a list of them, for someone's own words;
    $whyTitle and $why: a short list of reasons;
    $next: the next step, which then holds the action button. Emails without them read as plain lines, as before.
--}}
@php
    $facts = $facts ?? [];
    $why = $why ?? [];
    $next = $next ?? null;
    $quotes = isset($quote['text']) ? [$quote] : ($quote ?? []);
@endphp
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- The key facts in labelled cells, two or three to a row. --}}
@if ($facts)
<table class="status-card" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach (array_chunk($facts, count($facts) === 3 ? 3 : 2) as $row)
<tr>
@foreach ($row as [$label, $value])
<td class="status-cell" width="{{ (int) floor(100 / count($row)) }}%"><p class="status-label">{{ $label }}</p><p class="status-value">{{ $value }}</p></td>
@endforeach
</tr>
@endforeach
</table>

@endif
{{-- Someone's own words (the Registrar's message), kept as they wrote them. --}}
@if ($quotes)
<table class="status-card" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($quotes as $item)
<tr>
<td class="{{ $loop->first ? 'status-cell' : 'status-cell quote-more' }}">
<p class="status-label">{{ $item['label'] }}</p>
<p class="quote-text">{!! nl2br(e($item['text'])) !!}</p>
</td>
</tr>
@endforeach
</table>

@endif
{{-- Reasons, as a short list. --}}
@if ($why)
<p class="why-title">{{ $whyTitle ?? 'Why' }}</p>
<ul class="why-list">
@foreach ($why as $reason)
<li>{{ $reason }}</li>
@endforeach
</ul>

@endif
@if ($next)
{{-- Notes before the next step, then the step itself with its button. --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
<table class="next-step" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="next-step-cell">
<p class="status-label">{{ $next['title'] ?? 'Next step' }}</p>
<p class="next-step-text">{{ $next['text'] }}</p>
@isset($actionText)
<table class="action next-step-action" align="left" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="left">
<a href="{{ $actionUrl }}" class="button button-primary" target="_blank" rel="noopener">{{ $actionText }}</a>
</td>
</tr>
</table>
@endisset
</td>
</tr>
</table>

@else
{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@endif
{{-- A short crimson rule sets the sign-off apart, like a letter. --}}
<table class="rule" width="48" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="rule-cell" height="3" bgcolor="#c8003f">&nbsp;</td></tr></table>

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
