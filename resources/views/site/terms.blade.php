@extends('layouts.site')

@section('title', 'Terms & Conditions')
@section('description', 'Terms and Conditions for OASIS Research Community. Learn about our guidelines, user responsibilities, and legal agreements.')

@php
    // Each clause heading gets an anchor so the contents list can link to it; the wording itself is unchanged.
    $clauses = [];
    $body = preg_replace_callback('/<h3>(.*?)<\/h3>/s', function (array $match) use (&$clauses): string {
        $id = 'clause-'.(count($clauses) + 1);
        $clauses[$id] = strip_tags($match[1]);

        return '<h3 id="'.$id.'">'.$match[1].'</h3>';
    }, view('site.partials.terms-body')->render());
@endphp

@section('content')
    {{-- Terms & Conditions: a pale title band, then the clauses listed in a contents column beside the reading column. --}}
    <section class="pt-section bg-brand-paper px-page pb-16">
        <h1 class="font-display text-hero02 font-black">Terms &amp; Conditions</h1>
    </section>

    <div class="page-section pt-section bg-brand-sheet px-page">
        <div class="grid gap-x-16 gap-y-12 lg:grid-cols-12">
            @if ($clauses)
                <nav aria-label="Contents" class="min-w-0 lg:col-span-4">
                    <div class="lg:sticky lg:top-28">
                        @include('site.partials.section-divider', ['label' => 'Contents', 'tag' => 'h2', 'tight' => true])
                        <ol id="terms-contents" class="border-b border-brand-line">
                            @foreach ($clauses as $id => $title)
                                <li>
                                    <a href="#{{ $id }}" class="flex min-h-11 items-center gap-3 border-t border-brand-line py-2 font-semibold transition-colors hover:text-brand-crimson-strong data-current:text-brand-crimson-strong">
                                        <span aria-hidden="true" class="h-1.5 w-0 shrink-0 rounded-full bg-brand-crimson transition-[width] in-data-current:w-5"></span>{{ $title }}
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </nav>
            @endif

            <div class="article-body max-w-3xl min-w-0 lg:col-span-8">{!! $body !!}</div>
        </div>
    </div>

    <script>
        // Marks the clause being read in the contents list: the last clause whose heading has passed a line near the top.
        (() => {
            const links = [...document.querySelectorAll('#terms-contents a')];
            const headings = links.map((link) => document.getElementById(link.hash.slice(1))).filter(Boolean);
            if (!headings.length) return;
            let queued = false;
            const update = () => {
                queued = false;
                const line = window.innerHeight * 0.3;
                const current = headings.filter((heading) => heading.getBoundingClientRect().top <= line).pop();
                links.forEach((link) => link.toggleAttribute('data-current', current !== undefined && link.hash === '#' + current.id));
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
