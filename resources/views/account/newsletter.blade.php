@extends('layouts.site')

@section('title', 'Monthly emails')
@section('description', 'Stop or restart the OASIS Research Community monthly update.')

@section('content')
    {{-- From the "Stop these monthly emails" link in the monthly update. It asks first, so a mail scanner opening the link changes nothing. --}}
    <section class="pt-section bg-brand-paper px-page pb-section">
        <div class="mx-auto max-w-xl">
            <div class="rounded-card border border-brand-line bg-brand-sheet p-6 sm:p-10">
                @if ($user->newsletter_opted_out_at)
                    <span class="flex size-12 items-center justify-center rounded-full bg-brand-navy text-white" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <h1 class="mt-6 font-display text-heading02 font-black text-balance">The monthly update has stopped</h1>
                    <p class="mt-2 break-all text-body01 text-brand-navy/70">{{ $user->email }}</p>
                    <p class="mt-6 text-body01 text-brand-navy/80">
                        Receipts, reminders about your subscription, and emails about your application or exam still come.
                    </p>
                    <form method="POST" action="{{ $action }}" class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                        @csrf
                        <input type="hidden" name="stop" value="0">
                        <button type="submit" class="btn btn-quiet min-h-11">Send them again</button>
                        <a href="{{ url('/') }}" class="font-semibold text-brand-navy underline decoration-brand-line underline-offset-4 hover:decoration-current">Go to the site</a>
                    </form>
                @else
                    <h1 class="font-display text-heading02 font-black text-balance">Stop the monthly update?</h1>
                    <p class="mt-2 break-all text-body01 text-brand-navy/70">{{ $user->email }}</p>

                    <div class="mt-8 grid gap-6 border-t border-brand-line pt-6 sm:grid-cols-2">
                        <div>
                            <h2 class="text-sm font-semibold text-brand-crimson-strong">This stops</h2>
                            <ul class="mt-2 grid gap-1.5 text-body01">
                                <li>The monthly update: where your membership stands and what is new in the Knowledge Base</li>
                            </ul>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-brand-navy/70">These still come</h2>
                            <ul class="mt-2 grid list-disc gap-1.5 pl-5 text-body01">
                                <li>Receipts</li>
                                <li>Reminders about your subscription</li>
                                <li>Emails about your application or exam</li>
                            </ul>
                        </div>
                    </div>

                    <form method="POST" action="{{ $action }}" class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                        @csrf
                        <input type="hidden" name="stop" value="1">
                        <button type="submit" class="btn btn-ink min-h-11">Stop the monthly emails</button>
                        <a href="{{ url('/') }}" class="font-semibold text-brand-navy underline decoration-brand-line underline-offset-4 hover:decoration-current">Keep getting them</a>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
