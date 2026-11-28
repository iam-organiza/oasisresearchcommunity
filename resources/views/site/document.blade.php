@extends('layouts.site')

{{-- A long document (Terms & Conditions, Privacy Policy): a title band, then its clauses in a contents column beside the reading column. --}}
@section('title', $title)
@section('description', $description)

@php
    // Each clause heading gets an anchor so the contents list can link to it; the wording itself is unchanged.
    $clauses = [];
    $body = preg_replace_callback('/<h3>(.*?)<\/h3>/s', function (array $match) use (&$clauses): string {
        $id = 'clause-'.(count($clauses) + 1);
        // Decoded, as the list escapes it again: "&amp;" in the text shows as "&".
        $clauses[$id] = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5);

        // A leading clause number ("4.") gets its own span so it can hang in the margin.
        $heading = preg_replace('/^\s*(\d+\.)\s+/', '<span class="clause-number">$1</span> ', $match[1], 1);

        return '<h3 id="'.$id.'">'.$heading.'</h3>';
    }, $html);
@endphp

@section('content')
    <section class="pt-section bg-brand-paper px-page pb-16">
        <h1 class="font-display text-hero02 font-black">{{ $title }}</h1>
        @isset($updated)
            <p class="mt-4 text-body01 text-brand-navy/75">Last updated {{ $updated }}</p>
        @endisset
    </section>

    <div class="page-section pt-section bg-brand-sheet px-page">
        <div class="grid gap-x-16 gap-y-10 lg:grid-cols-12">
            @if ($clauses)
                {{-- One contents list: folded into a single row on phones and tablets, always open beside the text on desktop. --}}
                <nav aria-label="Contents" class="min-w-0 print:hidden lg:col-span-4">
                    <details id="document-contents-fold" class="group rounded-card border border-brand-line lg:sticky lg:top-28 lg:rounded-none lg:border-0">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 font-semibold lg:hidden [&::-webkit-details-marker]:hidden">
                            <span>Contents<span class="font-normal text-brand-navy/70">, {{ count($clauses) }} sections</span></span>
                            <svg viewBox="0 0 24 24" class="size-5 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="px-4 pb-2 lg:px-0 lg:pb-0">
                            <div class="hidden lg:block">
                                @include('site.partials.section-divider', ['label' => 'Contents', 'tag' => 'h2', 'tight' => true])
                            </div>
                            <ol id="document-contents" class="lg:border-b lg:border-brand-line">
                                @foreach ($clauses as $id => $title)
                                    <li>
                                        <a href="#{{ $id }}" class="flex min-h-11 items-center gap-3 border-t border-brand-line py-2 font-semibold transition-colors hover:text-brand-crimson-strong data-current:text-brand-crimson-strong">
                                            <span aria-hidden="true" class="h-1.5 w-0 shrink-0 rounded-full bg-brand-crimson transition-[width] in-data-current:w-5"></span>{{ $title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                            <div class="mt-5 hidden lg:block" aria-hidden="true">
                                <div class="h-1 overflow-hidden rounded-full bg-brand-line">
                                    <div id="document-progress" class="h-full w-0 rounded-full bg-brand-crimson"></div>
                                </div>
                                <p class="mt-2 text-caption01 text-brand-navy/70"><span id="document-progress-label">0</span>% read</p>
                            </div>
                        </div>
                    </details>
                    <script>
                        // Runs straight away so desktop never shows the list folded.
                        (() => {
                            const fold = document.getElementById('document-contents-fold');
                            const wide = window.matchMedia('(min-width: 64rem)');
                            const sync = () => { fold.open = wide.matches; };
                            sync();
                            wide.addEventListener('change', sync);
                            // On a phone, picking a clause folds the list away first, then goes to the clause,
                            // so the jump lands on the heading rather than where it was before the list closed.
                            fold.addEventListener('click', (event) => {
                                const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
                                const target = link ? document.getElementById(link.hash.slice(1)) : null;
                                if (wide.matches || !target) return;
                                event.preventDefault();
                                fold.open = false;
                                history.pushState(null, '', link.hash);
                                target.scrollIntoView();
                                target.setAttribute('tabindex', '-1');
                                target.focus({ preventScroll: true });
                            });
                        })();
                    </script>
                </nav>
            @endif

            <div id="document-body" class="article-body max-w-3xl min-w-0 lg:col-span-8">{!! $body !!}</div>
        </div>
    </div>

    {{-- Printing gives the document alone: no site header, footer or contents. --}}
    <style>
        @media print {
            body > :not(main) { display: none !important; }
        }
    </style>

    <script>
        // Marks the clause being read in the contents list (the last clause whose heading has passed a line near the top)
        // and how far through the document the reader is.
        (() => {
            const links = [...document.querySelectorAll('#document-contents a')];
            const headings = links.map((link) => document.getElementById(link.hash.slice(1))).filter(Boolean);
            const body = document.getElementById('document-body');
            const bar = document.getElementById('document-progress');
            const label = document.getElementById('document-progress-label');
            if (!headings.length || !body) return;
            let queued = false;
            const update = () => {
                queued = false;
                const line = window.innerHeight * 0.3;
                const current = headings.filter((heading) => heading.getBoundingClientRect().top <= line).pop();
                links.forEach((link) => link.toggleAttribute('data-current', current !== undefined && link.hash === '#' + current.id));
                if (bar && label) {
                    const box = body.getBoundingClientRect();
                    const span = box.height - window.innerHeight;
                    const read = span <= 0 ? 1 : Math.min(1, Math.max(0, -box.top / span));
                    const percent = Math.round(read * 100);
                    bar.style.width = percent + '%';
                    label.textContent = String(percent);
                }
            };
            window.addEventListener('scroll', () => {
                if (!queued) {
                    queued = true;
                    setTimeout(update, 60);
                }
            }, { passive: true });
            update();
        })();
    </script>
@endsection
