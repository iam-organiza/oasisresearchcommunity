@extends('layouts.site')

@section('title', 'Page not found')

@section('content')
    {{-- Page not found: the OASIS mark with one of its pages missing, beside the message and the ways back. --}}
    <section class="page-section pt-section bg-brand-paper px-page">
        <div class="grid items-center gap-x-16 gap-y-12 lg:min-h-[calc(100svh-18rem)] lg:grid-cols-12">
            <div class="lg:col-span-6">
                <p class="font-display text-hero01 font-black">404</p>
                <h1 class="mt-6 font-display text-heading01 font-black">Oops!</h1>
                <p class="mt-4 text-body01 text-brand-navy/75">We can't find that page.</p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('home') }}" class="btn btn-ink min-h-14 px-8 text-body01 max-sm:w-full">Return Home</a>
                    <a href="{{ route('knowledge-base') }}" class="btn btn-quiet min-h-14 px-8 text-body01 max-sm:w-full">Knowledge Base</a>
                </div>
            </div>

            {{-- The mark's top page is missing: only its dashed outline is left where it should be. --}}
            <svg viewBox="0 0 900 815" aria-hidden="true" class="mx-auto w-full max-w-xs sm:max-w-sm lg:col-span-6 lg:max-w-lg">
                <path d="M475 138 A290 290 0 1 1 304 551" fill="none" stroke="var(--color-brand-navy)" stroke-width="80" />
                <path d="M68 305 L612 440 L626 456 L482 577 L30 577 Z" fill="var(--color-brand-navy)" stroke="var(--color-brand-navy)" stroke-width="18" stroke-linejoin="round" />
                <path d="M182 103 L690 392 L684 410 L652 430 L158 300 Z" fill="var(--color-brand-crimson)" stroke="var(--color-brand-crimson)" stroke-width="18" stroke-linejoin="round" />
                <path d="M330 12 L698 380 L690 394 L312 148 Z" fill="none" stroke="var(--color-brand-crimson-strong)" stroke-width="8" stroke-linejoin="round" stroke-dasharray="22 16" />
            </svg>
        </div>
    </section>
@endsection
