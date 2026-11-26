@extends('layouts.site')

@php
    // The individual grades, lowest to highest (decided with OASIS on 2026-11-26), from App\Enums\Membership\Grade.
    $categories = collect(App\Enums\Membership\Grade::cases())->reverse()
        ->map(fn ($grade) => $grade === App\Enums\Membership\Grade::Fellow ? 'Fellows' : $grade->label().'s')
        ->values();
@endphp

@section('content')
    {{-- The landing page: full-width sections, each after the first opened by a labelled divider. --}}
    <div>

        {{-- Hero: one large statement beside the OASIS mark, whose three pages fan open once when the page loads. --}}
        <section class="page-section pt-section bg-brand-sheet px-page">
            <div class="grid items-center gap-x-16 gap-y-12 lg:min-h-[calc(100svh-14rem)] lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <h1 class="font-display text-hero01 font-black text-balance">OASIS Research Community</h1>
                    <p class="mt-6 font-display text-heading02 font-semibold">{{ config('oasis.tagline') }}</p>
                    <p class="mt-8 max-w-2xl text-body01 text-brand-navy/80">We believe that development at local, national or global levels are all greatly influenced by quality solution-driven research in government, academia and Industries</p>
                    <div class="mt-10 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="btn btn-primary min-h-14 px-8 text-body01 max-sm:w-full">Join Us</a>
                        <a href="{{ route('knowledge-base') }}" class="btn btn-quiet min-h-14 px-8 text-body01 max-sm:w-full">Read the Knowledge Base</a>
                    </div>
                </div>

                <svg viewBox="0 0 900 815" aria-hidden="true" class="mx-auto w-full max-w-sm sm:max-w-md lg:col-span-5 lg:max-w-none">
                    <path class="fan-page" d="M475 138 A290 290 0 1 1 304 551" fill="none" stroke="var(--color-brand-navy)" stroke-width="80" />
                    <path class="fan-page" d="M68 305 L612 440 L626 456 L482 577 L30 577 Z" fill="var(--color-brand-navy)" stroke="var(--color-brand-navy)" stroke-width="18" stroke-linejoin="round" />
                    <path class="fan-page" d="M182 103 L690 392 L684 410 L652 430 L158 300 Z" fill="var(--color-brand-crimson)" stroke="var(--color-brand-crimson)" stroke-width="18" stroke-linejoin="round" style="--fan-from: -15deg; animation-delay: 140ms" />
                    <path class="fan-page" d="M330 12 L698 380 L690 394 L312 148 Z" fill="var(--color-brand-crimson-strong)" stroke="var(--color-brand-crimson-strong)" stroke-width="18" stroke-linejoin="round" style="--fan-from: -31deg; animation-delay: 280ms" />
                </svg>
            </div>
        </section>

        {{-- What We Do: the four services as four tall pages in one row, each a different colour with a folded corner. --}}
        <section id="what-we-do" class="page-section bg-brand-paper px-page">
            @include('site.partials.section-divider', ['label' => 'What We Do'])
            <div class="grid gap-x-16 gap-y-6 lg:grid-cols-12 lg:items-end">
                <h2 class="font-display text-heading01 font-black lg:col-span-5">What We Do</h2>
                <p class="max-w-2xl text-body01 text-brand-navy/75 lg:col-span-7">We collaborate with our partner organizations, institutions and industry professionals to provide services essential to optimizing research quality and impact for professionals and enthusiasts</p>
            </div>

            <ul class="mt-14 grid gap-3 md:grid-cols-2 lg:mt-20 lg:gap-4 xl:grid-cols-4">
                @foreach ([
                    ['Training', 'We offer trainings and certifications on contemporary research methods and practices', 'bg-brand-sheet ring-1 ring-brand-line', 'text-brand-navy/75'],
                    ['Publishing', 'We offer high quality open-access peer-reviewed research publishing along with digitization, branding and digital marketing services for researchers', 'bg-brand-navy text-white', 'text-brand-on-dark'],
                    ['Consulting', 'We carry out as NEEDS, assessments, MEL, and other research projects on behalf of companies, governments and local/international NGOs', 'bg-brand-crimson-strong text-white', 'text-white/85'],
                    ['Membership', 'We induct and maintain a database of highly competent researchers as members of the OASIS Research Community', 'bg-brand-desk', 'text-brand-navy/75'],
                ] as [$service, $text, $tone, $textTone])
                    <li class="page-fold flex min-h-72 flex-col justify-between gap-12 rounded-card p-7 sm:p-8 xl:min-h-112 {{ $tone }}">
                        <h3 class="font-display text-heading01 font-black xl:text-heading02">{{ $service }}</h3>
                        <p class="max-w-md text-body01 xl:text-base {{ $textTone }}">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        {{--
            Membership: a two-tone split. Benefits on the navy half, the four individual categories (lowest to highest)
            and institutional membership on the white half, so the two parts read apart. On narrower screens the navy
            half sits above the white one.
        --}}
        <section id="membership" class="grid lg:grid-cols-2">
            <div class="page-section bg-brand-navy px-page text-white lg:pr-16 lg:pl-page-half">
                @include('site.partials.section-divider', ['label' => 'Membership'])
                <h2 class="font-display text-heading01 font-black">Membership Benefits</h2>
                <p class="mt-6 max-w-2xl text-body01 text-brand-on-dark">We operate membership system that delivers valuable benefits for members including subsidized access to our resources, networking, sharing of information on valuable opportunities, and paid consultancies, among other benefits.</p>

                <dl class="mt-12 space-y-8 lg:mt-16">
                    @foreach ([
                        'Information' => 'Frequent updates on research opportunities in academics and other industries, e.g. grants',
                        'Payment' => 'Paid participation on research consulting projects for our corporate clients and public sector.',
                        'Services' => 'Up to 100% waivers on publishing and author visibility services for subscribed members.',
                    ] as $benefit => $text)
                        <div class="border-t-2 border-brand-crimson pt-5">
                            <dt class="font-display text-heading02 font-black">{{ $benefit }}</dt>
                            <dd class="mt-2 max-w-xl text-body01 text-brand-on-dark">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="page-section bg-brand-sheet px-page lg:pr-page-half lg:pl-16">
                {{-- The divider's rule carries on across the white half, without repeating the label. --}}
                <div aria-hidden="true" class="section-divider max-lg:hidden"></div>
                <h2 id="categories-heading" class="font-display text-heading01 font-black max-lg:pt-section">Membership Categories</h2>
                <p class="mt-6 max-w-2xl text-body01 text-brand-navy/75">In line with its mandate to optimize the practice and outcomes of academic and industry research, the organization registers eligible persons who meet requirements for registration into any of the four (4) individual membership categories for which the organization maintains a register, and admits organizations as institutional members.</p>

                <ol aria-labelledby="categories-heading" class="mt-12 border-b border-brand-line lg:mt-16">
                    @foreach ($categories as $category)
                        <li class="flex items-baseline gap-6 border-t border-brand-line py-4 sm:gap-10">
                            <span class="w-6 shrink-0 text-caption01 font-semibold text-brand-navy/60 tabular-nums">{{ $loop->iteration }}</span>
                            <span @class(['font-display text-heading02 font-bold', 'text-brand-crimson-strong' => $loop->last])>{{ $category }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-6 flex items-baseline gap-6 sm:gap-10">
                    <span aria-hidden="true" class="w-6 shrink-0"></span>
                    <span>
                        <span class="font-display text-heading03 font-bold">Institutional Members</span>
                        <span class="mt-1 block text-body01 text-brand-navy/75">Universities, research institutes, companies and other organizations.</span>
                    </span>
                </p>
            </div>
        </section>

        {{--
            Featured Members: one member in the spotlight (portrait, name, grade and full description) beside the
            list of every featured member; choosing a name puts that member in the spotlight. On phones the list
            becomes a row of portraits above the spotlight. Without JavaScript every member is shown in turn.
        --}}
        <section id="featured-members" class="page-section bg-brand-desk px-page">
            @include('site.partials.section-divider', ['label' => 'Featured Members'])
            <h2 id="featured-heading" class="font-display text-heading01 font-black">Featured Members</h2>
            @if ($featuredMembers->isEmpty())
                <p class="mt-6 text-body01 text-brand-navy/75">Featured members will appear here.</p>
            @else
                <div data-spotlight class="mt-12 grid gap-x-16 gap-y-8 lg:mt-16 lg:grid-cols-12">
                    @if ($featuredMembers->count() > 1)
                        <div role="tablist" aria-labelledby="featured-heading" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-2 lg:order-last lg:col-span-4 lg:mx-0 lg:flex-col lg:gap-0 lg:overflow-visible lg:border-t lg:border-brand-line lg:px-0 lg:pb-0">
                            @foreach ($featuredMembers as $member)
                                <button type="button" role="tab" id="member-tab-{{ $loop->index }}" aria-controls="member-panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" class="group flex w-24 shrink-0 cursor-pointer flex-col items-center gap-2 rounded-control p-2 text-center transition-colors hover:bg-brand-sheet/70 aria-selected:bg-brand-sheet aria-selected:shadow-page lg:w-full lg:flex-row lg:gap-4 lg:rounded-none lg:border-b lg:border-brand-line lg:px-3 lg:py-3 lg:text-left">
                                    @if ($member->avatar_url)
                                        <img src="{{ $member->avatar_url }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-full bg-brand-desk object-cover object-top ring-2 ring-transparent transition group-aria-selected:ring-brand-crimson lg:size-12">
                                    @else
                                        <span class="size-16 shrink-0 rounded-full bg-brand-desk ring-2 ring-transparent group-aria-selected:ring-brand-crimson lg:size-12"></span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block text-caption01 font-semibold lg:text-base lg:font-bold">{{ $member->full_name }}</span>
                                        @if ($member->position)
                                            <span class="hidden text-caption01 text-brand-navy/60 lg:block">{{ $member->position }}</span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="lg:col-span-8">
                        @foreach ($featuredMembers as $member)
                            <article id="member-panel-{{ $loop->index }}" role="tabpanel" aria-labelledby="member-tab-{{ $loop->index }}" @class(['grid gap-8 sm:grid-cols-2 sm:gap-10', 'mt-12' => ! $loop->first]) data-member-panel>
                                @if ($member->avatar_url)
                                    <img src="{{ $member->avatar_url }}" alt="Portrait of {{ $member->full_name }}" loading="lazy" class="aspect-4/5 w-full rounded-card bg-brand-desk object-cover object-top">
                                @else
                                    <div class="aspect-4/5 w-full rounded-card bg-brand-desk"></div>
                                @endif
                                <div class="sm:pt-4">
                                    <h3 class="font-display text-heading02 font-black">{{ $member->full_name }}</h3>
                                    @if ($member->position)
                                        <p class="mt-2 font-semibold text-brand-crimson-strong">{{ $member->position }}</p>
                                    @endif
                                    @if ($member->job_description)
                                        <p class="mt-5 text-body01 whitespace-pre-line text-brand-navy/80">{{ $member->job_description }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                @if ($featuredMembers->count() > 1)
                    <script>
                        // Puts the chosen member in the spotlight; arrow keys move between names, as in any tab list.
                        (() => {
                            const root = document.querySelector('[data-spotlight]');
                            const tabs = [...root.querySelectorAll('[role="tab"]')];
                            const panels = [...root.querySelectorAll('[data-member-panel]')];
                            const show = (index, focus) => {
                                tabs.forEach((tab, i) => {
                                    tab.setAttribute('aria-selected', String(i === index));
                                    tab.tabIndex = i === index ? 0 : -1;
                                });
                                panels.forEach((panel, i) => {
                                    panel.hidden = i !== index;
                                    panel.classList.remove('mt-12');
                                });
                                if (focus) tabs[index].focus();
                                tabs[index].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                            };
                            tabs.forEach((tab, index) => {
                                tab.addEventListener('click', () => show(index, false));
                                tab.addEventListener('keydown', (event) => {
                                    const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
                                    if (step === undefined) return;
                                    event.preventDefault();
                                    show((index + step + tabs.length) % tabs.length, true);
                                });
                            });
                            panels.forEach((panel, i) => {
                                panel.hidden = i !== 0;
                                panel.classList.remove('mt-12');
                            });
                        })();
                    </script>
                @endif
            @endif
        </section>

        {{-- Join: crimson, left-aligned, with the OASIS mark very large and faint, cropped at the right edge. --}}
        <section class="page-section relative overflow-clip bg-brand-crimson-strong px-page text-white">
            <svg viewBox="0 0 900 815" aria-hidden="true" class="pointer-events-none absolute top-1/2 -right-24 hidden w-[min(62vw,52rem)] -translate-y-1/2 text-white opacity-10 sm:block lg:-right-16">
                <path d="M475 138 A290 290 0 1 1 304 551" fill="none" stroke="currentColor" stroke-width="80" />
                <path d="M68 305 L612 440 L626 456 L482 577 L30 577 Z" fill="currentColor" stroke="currentColor" stroke-width="18" stroke-linejoin="round" />
                <path d="M182 103 L690 392 L684 410 L652 430 L158 300 Z" fill="currentColor" stroke="currentColor" stroke-width="18" stroke-linejoin="round" />
                <path d="M330 12 L698 380 L690 394 L312 148 Z" fill="currentColor" stroke="currentColor" stroke-width="18" stroke-linejoin="round" />
            </svg>

            <div class="relative">
                @include('site.partials.section-divider', ['label' => 'Join Us'])
                <h2 class="max-w-4xl font-display text-hero02 font-black text-balance">Join Our Community of Research Professionals to Stay Ahead</h2>
                <div class="mt-10 flex flex-wrap gap-3 lg:mt-14">
                    <a href="{{ route('register') }}" class="btn btn-light min-h-14 px-10 text-body01 max-sm:w-full">Join Us</a>
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-on-dark min-h-14 px-8 text-body01 max-sm:w-full">Sign in</a>
                    @endguest
                </div>
            </div>
        </section>

        {{--
            About Us: the introduction, then the three core values set very large across the width as the centrepiece,
            vision and mission under them, and the recognition closing the section like the registration line on an
            official document.
        --}}
        <section id="about-us" class="page-section bg-brand-sheet px-page">
            @include('site.partials.section-divider', ['label' => 'About Us'])
            <div class="grid gap-x-16 gap-y-6 lg:grid-cols-12">
                <h2 class="font-display text-heading01 font-black lg:col-span-5">About Us</h2>
                <p class="text-body01 text-pretty text-brand-navy/80 lg:col-span-7">OASIS Research Community was established to advance the practice of high-quality research by providing research training and support to research enthusiasts and professionals around the world, particularly from developing countries like Nigeria. OASIS creates and operates platforms that promote and optimize intellectual insights, research capabilities and research outcomes for our members and corporate clients.</p>
            </div>

            <dl class="mt-16 grid gap-x-16 md:grid-cols-2 lg:mt-24">
                <div class="border-y-2 border-brand-navy py-8 md:col-span-2 lg:py-12">
                    <dt class="text-caption01 font-semibold text-brand-crimson-strong">Our Core Values</dt>
                    <dd class="mt-4 flex flex-wrap justify-between gap-x-10 gap-y-2 font-display text-hero02 font-black">
                        <span>Excellence.</span>
                        <span>Ethics.</span>
                        <span>Integrity.</span>
                    </dd>
                </div>

                @foreach ([
                    'Our Vision' => 'To sustainably optimize academic and professional research outcomes for stakeholders',
                    'Our Mission' => 'To entrench global research best practices in the academic and professional research community through research education, support, publishing, and marketing',
                ] as $term => $text)
                    <div class="mt-12 lg:mt-16">
                        <dt class="font-display text-heading03 font-bold text-brand-crimson-strong">{{ $term }}</dt>
                        <dd class="mt-3 font-display text-heading02 font-semibold text-balance">{{ $text }}</dd>
                    </div>
                @endforeach

                <div class="mt-16 flex flex-col gap-x-10 gap-y-2 border-t border-brand-line pt-6 sm:flex-row md:col-span-2 lg:mt-20">
                    <dt class="shrink-0 text-caption01 font-semibold">Our Recognition</dt>
                    <dd class="max-w-4xl text-caption01 text-brand-navy/70">OASIS Research Community is registered in Nigeria with the Corporate Affairs Commission (CAC) as a Company Limited by Guarantee with RC No:2008295 and authorization of the Attorney General of the Federation, the Federal Ministry of Education and the National Board of Technical Education (NBTE).</dd>
                </div>
            </dl>
        </section>

        {{--
            Contact Us: the map fills the section edge to edge and a white card lies over its right side with the ways to
            reach the secretariat, like an address card laid on a map, leaving the map's own controls at the top left
            uncovered. The map stays draggable around the card. On narrower
            screens the card comes first and the map sits below it.
        --}}
        <section id="contact-us" class="page-section pt-section relative flex flex-col bg-brand-paper lg:block lg:min-h-176">
            <div class="order-last mt-10 px-page lg:absolute lg:inset-0 lg:mt-0 lg:px-0">
                <iframe src="{{ config('oasis.map_embed_url') }}" title="Map showing the National Secretariat, 11 Banjul Street, Wuse 2, Abuja" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen class="block h-80 w-full rounded-card border-0 lg:h-full lg:rounded-none"></iframe>
            </div>

            <div class="relative px-page lg:pointer-events-none">
                <div class="max-w-xl rounded-card bg-brand-sheet p-7 shadow-page ring-1 ring-brand-line sm:p-10 lg:pointer-events-auto lg:ml-auto">
                    @include('site.partials.section-divider', ['label' => 'Contact Us'])
                    <h2 class="font-display text-heading01 font-black">Contact Us</h2>
                    <dl class="mt-10 border-b border-brand-line">
                        <div class="border-t border-brand-line py-5">
                            <dt class="text-caption01 font-semibold text-brand-navy/60">Send Us Email</dt>
                            <dd class="mt-1"><a href="mailto:{{ config('oasis.support_email') }}" class="font-display text-heading03 font-bold underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong hover:decoration-brand-crimson">{!! str_replace('@', '@<wbr>', e(config('oasis.support_email'))) !!}</a></dd>
                        </div>
                        <div class="border-t border-brand-line py-5">
                            <dt class="text-caption01 font-semibold text-brand-navy/60">Let's Speak</dt>
                            <dd class="mt-1 flex flex-wrap gap-x-8 gap-y-1">
                                @foreach (config('oasis.phones') as $phone)
                                    <a href="tel:{{ str_replace(' ', '', $phone) }}" class="font-display text-heading03 font-bold hover:text-brand-crimson-strong">{{ $phone }}</a>
                                @endforeach
                            </dd>
                        </div>
                        <div class="border-t border-brand-line py-5">
                            <dt class="text-caption01 font-semibold text-brand-navy/60">{{ config('oasis.address.0') }}</dt>
                            <dd class="mt-1 font-display text-heading03 font-bold">{{ config('oasis.address.1') }} {{ config('oasis.address.2') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </div>
@endsection
