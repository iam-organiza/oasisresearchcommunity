@extends('layouts.site')

@section('title', 'Monthly emails')
@section('description', 'Stop or restart the OASIS Research Community monthly update.')

@section('content')
    {{-- From the "Stop these monthly emails" link in the monthly update. It asks first, so a mail scanner opening the link changes nothing. --}}
    <section class="pt-section bg-brand-paper px-page pb-section">
        <div class="max-w-2xl">
            <h1 class="font-display text-hero02 font-black">Monthly emails</h1>

            <div class="mt-10 rounded-card border border-brand-line bg-brand-sheet p-8">
                @if ($user->newsletter_opted_out_at)
                    <p class="font-display text-heading02 font-bold">You won't get the monthly update any more.</p>
                    <p class="mt-3 text-body01 text-brand-navy/75">
                        Receipts and reminders about your subscription still come by email to {{ $user->email }}.
                    </p>
                    <form method="POST" action="{{ $action }}" class="mt-8">
                        @csrf
                        <input type="hidden" name="stop" value="0">
                        <button type="submit" class="btn btn-quiet min-h-11">Send them again</button>
                    </form>
                @else
                    <p class="text-body01 text-brand-navy/75">
                        The monthly update tells you where your membership stands and what is new in the Knowledge Base.
                        Receipts and reminders about your subscription come either way.
                    </p>
                    <p class="mt-6 font-display text-heading02 font-bold">Stop the monthly update for {{ $user->email }}?</p>
                    <form method="POST" action="{{ $action }}" class="mt-8">
                        @csrf
                        <input type="hidden" name="stop" value="1">
                        <button type="submit" class="btn btn-ink min-h-11">Stop the monthly emails</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
