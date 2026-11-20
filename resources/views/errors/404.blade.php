@extends('layouts.site')

@section('title', 'Page not found')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6">
        <p class="font-display text-8xl leading-none font-semibold text-brand-crimson-strong">404</p>
        <h1 class="mt-6 font-display text-4xl font-semibold tracking-tight">Oops!</h1>
        <p class="mt-3 text-lg text-muted-foreground">We can't find that page.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}" class="inline-flex min-h-12 items-center rounded-full bg-brand-navy px-7 font-semibold text-white hover:bg-brand-crimson-strong">Return Home</a>
            <a href="{{ route('knowledge-base') }}" class="inline-flex min-h-12 items-center rounded-full border border-input px-7 font-semibold hover:border-brand-navy">Knowledge Base</a>
        </div>
    </div>
@endsection
