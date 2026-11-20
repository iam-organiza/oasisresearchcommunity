@php
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle === '' ? config('app.name') : $pageTitle.' - '.config('app.name');
    $description = trim($__env->yieldContent('description')) ?: 'A professional body established to optimize the practice of research in Nigeria through training, publishing, consulting and membership.';
    $image = trim($__env->yieldContent('image')) ?: asset('images/oasis-logo.png');
    $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $nav = [
        'Home' => route('home'),
        'What We Do' => route('home').'#what-we-do',
        'Membership' => route('home').'#membership',
        'Featured Members' => route('home').'#featured-members',
        'Knowledge Base' => route('knowledge-base'),
        'About Us' => route('home').'#about-us',
        'Contact Us' => route('home').'#contact-us',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:title" content="{{ trim($__env->yieldContent('og_title')) ?: $fullTitle }}">
        <meta property="og:description" content="{{ trim($__env->yieldContent('og_description')) ?: $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $image }}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @fonts
        @vite(['resources/css/app.css'])
        @stack('head')
    </head>
    <body class="bg-white font-sans text-brand-navy antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

        <div class="bg-brand-navy text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-end gap-x-5 gap-y-1 px-4 py-2 text-sm sm:px-6">
                <span class="text-white/70">Follow us</span>
                @foreach (config('oasis.social') as $name => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="font-medium hover:underline">{{ $name }}</a>
                @endforeach
            </div>
        </div>

        <header class="border-b border-border bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="shrink-0">
                    <img src="/images/oasis-logo.png" alt="OASIS Research Community" width="900" height="336" class="h-11 w-auto">
                </a>

                <nav aria-label="Main" class="hidden items-center gap-6 text-[15px] font-medium lg:flex">
                    @foreach ($nav as $label => $url)
                        <a href="{{ $url }}" class="hover:text-brand-crimson-strong">{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-3">
                    @auth
                        @if (auth()->user()->role->isStaff())
                            <a href="{{ route('admin') }}" class="inline-flex min-h-11 items-center rounded-full bg-brand-crimson-strong px-5 font-semibold text-white hover:bg-brand-navy">Dashboard</a>
                        @else
                            <a href="{{ route('profile.edit') }}" class="inline-flex min-h-11 items-center rounded-full bg-brand-crimson-strong px-5 font-semibold text-white hover:bg-brand-navy">My account</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full bg-brand-crimson-strong px-5 font-semibold text-white hover:bg-brand-navy">Sign in</a>
                    @endauth

                    <details class="relative lg:hidden">
                        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-full border border-input px-4 font-semibold">Menu</summary>
                        <nav aria-label="Main" class="absolute right-0 z-20 mt-2 flex w-60 flex-col rounded-xl border border-border bg-white p-2 shadow-lg">
                            @foreach ($nav as $label => $url)
                                <a href="{{ $url }}" class="rounded-lg px-3 py-3 font-medium hover:bg-brand-paper">{{ $label }}</a>
                            @endforeach
                        </nav>
                    </details>
                </div>
            </div>
        </header>

        <main id="main">
            @yield('content')
        </main>

        <footer class="bg-brand-navy text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-4 px-4 py-8 text-sm sm:px-6">
                <div class="flex flex-wrap gap-x-6 gap-y-2 font-medium">
                    <a href="{{ route('terms') }}" class="hover:underline">Terms &amp; Conditions</a>
                    <a href="{{ route('knowledge-base') }}" class="hover:underline">Knowledge Base</a>
                    <a href="{{ route('home') }}#contact-us" class="hover:underline">Contact</a>
                </div>
                <p class="text-white/75">Copyright &copy; {{ date('Y') }} OASIS Research Community. All rights reserved.</p>
            </div>
        </footer>
    </body>
</html>
