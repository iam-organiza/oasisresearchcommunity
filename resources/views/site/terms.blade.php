@extends('layouts.site')

@section('title', 'Terms & Conditions')
@section('description', 'Terms and Conditions for OASIS Research Community. Learn about our guidelines, user responsibilities, and legal agreements.')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <h1 class="font-display text-5xl font-semibold tracking-tight">Terms &amp; Conditions</h1>
        <div class="article-body mt-8">
            @include('site.partials.terms-body')
        </div>
    </div>
@endsection
