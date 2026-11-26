@extends('layouts.site')

@section('title', 'Referee request')
@section('description', 'Tell OASIS Research Community whether you can vouch for an applicant.')

@section('content')
    {{-- A referee answers from their email link: one question, two buttons, then a thank-you. --}}
    <section class="pt-section bg-brand-paper px-page pb-section">
        <div class="max-w-2xl">
            <h1 class="font-display text-hero02 font-black">Referee request</h1>

            <div class="mt-10 rounded-card border border-brand-line bg-brand-sheet p-8">
                @if ($application->referee_status)
                    <p class="font-display text-heading02 font-bold">Thank you, {{ $application->referee_name }}.</p>
                    <p class="mt-3 text-body01 text-brand-navy/75">
                        @if ($application->referee_status === 'confirmed')
                            We have recorded that you can vouch for {{ $application->user->name }}. The Registrar will see your answer.
                        @else
                            We have recorded that you cannot vouch for {{ $application->user->name }}. The Registrar will see your answer.
                        @endif
                    </p>
                @else
                    <p class="text-body01 text-brand-navy/75">
                        {{ $application->user->name }} has applied to join OASIS Research Community and named you as a referee
                        ({{ $application->referee_relationship }}).
                    </p>
                    <p class="mt-6 font-display text-heading02 font-bold">Can you vouch for their character and their work in research?</p>

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
