@extends('layouts.site')

@section('title', 'Referee request')
@section('description', 'Tell OASIS Research Community whether you can vouch for an applicant.')

@section('content')
    {{-- A referee answers from their email link: one question, two buttons, then a thank-you.
         Not sent at present (the referee step was dropped); kept for when referees return on the profile. --}}
    <section class="pt-section bg-brand-paper px-page pb-section">
        <div class="mx-auto max-w-xl">
            <div class="rounded-card border border-brand-line bg-brand-sheet p-6 sm:p-10">
                @if ($application->referee_status)
                    <span class="flex size-12 items-center justify-center rounded-full bg-brand-navy text-white" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <h1 class="mt-6 font-display text-heading02 font-black text-balance">Thank you, {{ $application->referee_name }}</h1>
                    <p class="mt-4 text-body01 text-brand-navy/80">
                        @if ($application->referee_status === 'confirmed')
                            We have recorded that you can vouch for {{ $application->user->name }}.
                        @else
                            We have recorded that you cannot vouch for {{ $application->user->name }}.
                        @endif
                        The Registrar sees your answer with their application. There is nothing more to do.
                    </p>
                    <p class="mt-8">
                        <a href="{{ url('/') }}" class="font-semibold text-brand-navy underline decoration-brand-line underline-offset-4 hover:decoration-current">Go to the site</a>
                    </p>
                @else
                    <p class="text-sm font-semibold text-brand-navy/70">Referee request</p>
                    <h1 class="mt-2 font-display text-heading02 font-black text-balance">Can you vouch for {{ $application->user->name }}?</h1>
                    <p class="mt-4 text-body01 text-brand-navy/80">
                        They have applied to join {{ config('app.name') }} and named you as a referee{{ $application->referee_relationship ? ' ('.$application->referee_relationship.')' : '' }}.
                        We are asking whether you can vouch for their character and their work in research.
                    </p>
                    <p class="mt-3 text-sm text-brand-navy/70">
                        Your answer goes only to the Registrar, with their application. You don't need an account.
                    </p>

                    <form method="POST" action="{{ $answerUrl }}" class="mt-8 flex flex-wrap gap-3">
                        @csrf
                        <button type="submit" name="answer" value="confirmed" class="btn btn-ink min-h-11">Yes, I can vouch for them</button>
                        <button type="submit" name="answer" value="declined" class="btn btn-quiet min-h-11">No, I cannot</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
