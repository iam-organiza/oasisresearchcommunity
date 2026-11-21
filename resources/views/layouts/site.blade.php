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
{{-- Links to a section glide there (unless reduced motion is on) and stop just below the sticky header. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-24 motion-safe:scroll-smooth">
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
    <body class="bg-brand-sheet font-sans text-brand-navy antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

        {{-- Header: sits on the grey desk above the page; it stays at the top and gains a rule once the page scrolls. --}}
        <div class="hidden bg-brand-desk px-page sm:block">
            <div class="flex items-center justify-end gap-x-1 border-b border-brand-line py-1 text-caption01">
                <span class="mr-2 text-brand-navy/60">Follow us</span>
                @foreach (config('oasis.social') as $name => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $name }}" title="{{ $name }}" class="flex size-8 items-center justify-center rounded-control transition-colors hover:bg-brand-sheet hover:text-brand-crimson-strong">
                        @include('site.partials.social-icon', ['name' => $name, 'class' => 'size-4'])
                    </a>
                @endforeach
            </div>
        </div>

        <header id="site-header" class="sticky top-0 z-30 border-b border-transparent bg-brand-desk px-page transition-colors data-scrolled:border-brand-line">
            <div class="relative flex h-20 items-center justify-between gap-6">
                <a href="{{ route('home') }}" class="shrink-0">
                    <img src="/images/oasis-logo.png" alt="OASIS Research Community" width="900" height="336" class="h-11 w-auto">
                </a>

                {{--
                    Wide screens: plain links; the current page, or on the landing page the section in view, is a small
                    white sheet, like a tab on a file.
                --}}
                <nav aria-label="Main" class="hidden items-center gap-1 xl:flex">
                    @foreach ($nav as $label => $url)
                        @php($current = ($label === 'Home' && request()->routeIs('home')) || ($label === 'Knowledge Base' && request()->routeIs('knowledge-base', 'posts.*')))
                        <a href="{{ $url }}" data-nav-link @if ($current) aria-current="page" data-current @endif class="rounded-control px-3 py-2 text-caption01 font-semibold whitespace-nowrap transition-colors hover:bg-brand-sheet/60 data-current:bg-brand-sheet data-current:shadow-page">{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2">
                    <div class="hidden items-center gap-2 sm:flex">
                        @include('site.partials.account-actions')
                    </div>

                    {{--
                        Narrower screens: Menu opens a white card. The two pages come first in large type, then the
                        home page's sections one per row, the account buttons (on phones) and the platform logos.
                    --}}
                    <details id="site-menu" class="group xl:hidden">
                        <summary class="btn btn-quiet min-h-11 cursor-pointer list-none group-open:bg-brand-navy group-open:text-white group-open:shadow-none [&::-webkit-details-marker]:hidden">
                            <span class="group-open:hidden">Menu</span>
                            <span class="hidden group-open:inline">Close</span>
                        </summary>
                        <div class="absolute inset-x-0 top-full mt-2 max-h-[calc(100svh-6rem)] overflow-y-auto rounded-card bg-brand-sheet p-5 shadow-page ring-1 ring-brand-line transition duration-200 starting:-translate-y-2 starting:opacity-0 sm:p-7">
                            <nav aria-label="Main">
                                <ul class="divide-y divide-brand-line border-b border-brand-line">
                                    @foreach (['Home', 'Knowledge Base'] as $label)
                                        @php($current = ($label === 'Home' && request()->routeIs('home')) || ($label === 'Knowledge Base' && request()->routeIs('knowledge-base', 'posts.*')))
                                        <li>
                                            <a href="{{ $nav[$label] }}" @if ($current) aria-current="page" @endif class="flex items-center gap-4 py-4 font-display text-heading02 font-black hover:text-brand-crimson-strong">
                                                {{ $label }}
                                                @if ($current)
                                                    <span aria-hidden="true" class="h-1.5 w-8 rounded-full bg-brand-crimson"></span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>

                                <p class="mt-6 text-caption01 font-semibold text-brand-navy/60">On the home page</p>
                                <ul class="mt-3 divide-y divide-brand-line overflow-hidden rounded-control bg-brand-paper">
                                    @foreach (['What We Do', 'Membership', 'Featured Members', 'About Us', 'Contact Us'] as $label)
                                        <li>
                                            <a href="{{ $nav[$label] }}" class="flex min-h-12 items-center px-4 font-semibold transition-colors hover:bg-brand-navy hover:text-white">{{ $label }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </nav>

                            <div class="mt-6 grid grid-cols-2 gap-2 sm:hidden [&>*:only-child]:col-span-2">
                                @include('site.partials.account-actions')
                            </div>

                            <div class="mt-6 flex items-center justify-between gap-4 border-t border-brand-line pt-5">
                                <span class="text-caption01 font-semibold text-brand-navy/60">Follow us</span>
                                <div class="flex gap-2">
                                    @foreach (config('oasis.social') as $name => $url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $name }}" class="flex size-11 items-center justify-center rounded-control bg-brand-paper transition-colors hover:bg-brand-navy hover:text-white">
                                            @include('site.partials.social-icon', ['name' => $name])
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </header>
        <script>
            // Marks the header once the page has moved, so it shows its bottom rule.
            (() => {
                const header = document.getElementById('site-header');
                const mark = () => header.toggleAttribute('data-scrolled', window.scrollY > 8);
                mark();
                window.addEventListener('scroll', mark, { passive: true });

                // On the landing page, the header marks the section in view, and Home glides back to the top.
                // This runs once the page has loaded, since the sections come after the header.
                document.addEventListener('DOMContentLoaded', () => {
                    const sections = [...document.querySelectorAll('main section[id]')];
                    const links = [...document.querySelectorAll('[data-nav-link]')];
                    if (sections.length && location.pathname === '/') {
                        const home = links.find((link) => new URL(link.href).hash === '');
                        const mark = (id) => {
                            links.forEach((link) => {
                                const url = new URL(link.href);
                                const isCurrent = url.pathname === '/' && (id ? url.hash === '#' + id : url.hash === '');
                                if (url.pathname !== '/') return;
                                link.toggleAttribute('data-current', isCurrent);
                                if (isCurrent) link.setAttribute('aria-current', id ? 'location' : 'page');
                                else link.removeAttribute('aria-current');
                            });
                        };
                        // The section in view is the last one whose top has passed a line 40% of the way down the screen.
                        let queued = false;
                        const update = () => {
                            queued = false;
                            const line = window.innerHeight * 0.4;
                            const current = sections.filter((section) => section.getBoundingClientRect().top <= line).pop();
                            mark(current ? current.id : null);
                        };
                        window.addEventListener('scroll', () => {
                            if (!queued) {
                                queued = true;
                                setTimeout(update, 60);
                            }
                        }, { passive: true });
                        update();
                        home?.addEventListener('click', (event) => {
                            event.preventDefault();
                            history.pushState(null, '', '/');
                            window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                        });
                    }
                });

                // The menu closes after a link in it is chosen, on Escape, or on a click outside it.
                const menu = document.getElementById('site-menu');
                menu.addEventListener('click', (event) => {
                    if (event.target.closest('a')) menu.open = false;
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && menu.open) {
                        menu.open = false;
                        menu.querySelector('summary').focus();
                    }
                });
                document.addEventListener('click', (event) => {
                    if (menu.open && !menu.contains(event.target)) menu.open = false;
                });
            })();
        </script>

        <main id="main">
            @yield('content')
        </main>

        {{-- Footer: navy, with the links and contact details in columns. --}}
        <footer class="bg-brand-navy px-page pt-16 pb-8 text-white lg:pt-20">
            <div class="grid grid-cols-2 gap-x-8 gap-y-12 sm:gap-x-12 lg:grid-cols-12">
                <div class="col-span-2 lg:col-span-4">
                    <a href="{{ route('home') }}" class="inline-block">
                        <img src="/images/oasis-logo-white.png" alt="OASIS Research Community" width="900" height="336" loading="lazy" class="h-14 w-auto">
                    </a>
                    <p class="mt-5 font-display text-heading03 font-bold">{{ config('oasis.tagline') }}</p>
                </div>

                <nav aria-label="Footer" class="col-span-2 sm:col-span-1 lg:col-span-4">
                    <ul class="grid grid-cols-2 gap-x-6 gap-y-1">
                        @foreach ($nav as $label => $url)
                            <li><a href="{{ $url }}" class="inline-flex min-h-10 items-center font-semibold text-brand-on-dark hover:text-white">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <div class="col-span-2 sm:col-span-1 lg:col-span-4">
                    <ul class="space-y-1 font-semibold">
                        <li><a href="mailto:{{ config('oasis.support_email') }}" class="inline-flex min-h-10 items-center text-brand-on-dark hover:text-white">{!! str_replace('@', '@<wbr>', e(config('oasis.support_email'))) !!}</a></li>
                        @foreach (config('oasis.phones') as $phone)
                            <li><a href="tel:{{ str_replace(' ', '', $phone) }}" class="inline-flex min-h-10 items-center text-brand-on-dark hover:text-white">{{ $phone }}</a></li>
                        @endforeach
                    </ul>
                    <div class="mt-5 flex gap-2">
                        @foreach (config('oasis.social') as $name => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $name }}" title="{{ $name }}" class="flex size-11 items-center justify-center rounded-control bg-white/10 transition-colors hover:bg-white hover:text-brand-navy">
                                @include('site.partials.social-icon', ['name' => $name])
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-14 flex flex-wrap items-center justify-between gap-x-8 gap-y-2 border-t border-white/15 pt-6 text-caption01">
                <p class="text-brand-on-dark">Copyright &copy; {{ date('Y') }} OASIS Research Community. All rights reserved.</p>
                <a href="{{ route('terms') }}" class="inline-flex min-h-10 items-center font-semibold hover:underline">Terms &amp; Conditions</a>
            </div>
        </footer>
    </body>
</html>
