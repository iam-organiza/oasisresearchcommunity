@extends('layouts.site')

@section('content')
    <section class="bg-brand-navy text-white">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:py-28">
            <h1 class="max-w-3xl font-display text-5xl leading-[1.05] font-semibold tracking-tight sm:text-6xl">OASIS Research Community</h1>
            <p class="mt-4 font-display text-2xl text-white/85">{{ config('oasis.tagline') }}</p>
            <a href="{{ route('register') }}" class="mt-8 inline-flex min-h-12 items-center rounded-full bg-brand-crimson-strong px-7 font-semibold text-white hover:bg-white hover:text-brand-navy">Join Us</a>
            <blockquote class="mt-14 max-w-3xl border-l-4 border-brand-crimson pl-5 text-lg leading-relaxed text-white/90">
                We believe that development at local, national or global levels are all greatly influenced by quality solution-driven research in government, academia and Industries
            </blockquote>
        </div>
    </section>

    <section id="what-we-do" class="scroll-mt-6">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="font-display text-4xl font-semibold tracking-tight">What We Do</h2>
            <p class="mt-3 max-w-3xl text-lg text-muted-foreground">We collaborate with our partner organizations, institutions and industry professionals to provide services essential to optimizing research quality and impact for professionals and enthusiasts</p>
            <div class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                @foreach ([
                    'Training' => 'We offer trainings and certifications on contemporary research methods and practices',
                    'Publishing' => 'We offer high quality open-access peer-reviewed research publishing along with digitization, branding and digital marketing services for researchers',
                    'Consulting' => 'We carry out as NEEDS, assessments, MEL, and other research projects on behalf of companies, governments and local/international NGOs',
                    'Membership' => 'We induct and maintain a database of highly competent researchers as members of the OASIS Research Community',
                ] as $service => $text)
                    <div class="border-t-2 border-brand-navy pt-4">
                        <h3 class="font-display text-2xl font-semibold">{{ $service }}</h3>
                        <p class="mt-2 text-muted-foreground">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="membership" class="scroll-mt-6 bg-brand-paper">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="font-display text-4xl font-semibold tracking-tight">Membership Benefits</h2>
            <p class="mt-3 max-w-3xl text-lg text-muted-foreground">We operate membership system that delivers valuable benefits for members including subsidized access to our resources, networking, sharing of information on valuable opportunities, and paid consultancies, among other benefits.</p>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ([
                    'Information' => 'Frequent updates on research opportunities in academics and other industries, e.g. grants',
                    'Payment' => 'Paid participation on research consulting projects for our corporate clients and public sector.',
                    'Services' => 'Up to 100% waivers on publishing and author visibility services for subscribed members.',
                ] as $benefit => $text)
                    <div class="rounded-2xl bg-white p-6">
                        <h3 class="font-display text-2xl font-semibold">{{ $benefit }}</h3>
                        <p class="mt-2 text-muted-foreground">{{ $text }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-16 grid gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-4xl font-semibold tracking-tight">Membership Categories</h2>
                    <p class="mt-3 text-lg text-muted-foreground">In line with its mandate to optimize the practice and outcomes of academic and industry research, the organization registers eligible persons who meet requirements for registration into any of the six (6) membership categories for which the organization maintains a register</p>
                </div>
                <ul class="grid grid-cols-2 gap-3 self-start">
                    @foreach (['Fellows', 'Senior Members', 'Full Members', 'Associate Members', 'Graduate Members', 'Student Members'] as $category)
                        <li class="rounded-xl border border-border bg-white px-4 py-3 font-display text-lg font-medium">{{ $category }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section id="featured-members" class="scroll-mt-6">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="font-display text-4xl font-semibold tracking-tight">Featured Members</h2>
            @if ($featuredMembers->isEmpty())
                <p class="mt-4 text-muted-foreground">Featured members will appear here.</p>
            @else
                <ul class="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($featuredMembers as $member)
                        <li>
                            @if ($member->avatar_url)
                                <img src="{{ $member->avatar_url }}" alt="" loading="lazy" class="aspect-square w-full rounded-2xl bg-brand-paper object-cover">
                            @else
                                <div class="aspect-square w-full rounded-2xl bg-brand-paper"></div>
                            @endif
                            <h3 class="mt-4 font-display text-xl font-semibold">{{ $member->full_name }}</h3>
                            @if ($member->position)
                                <p class="font-medium text-brand-crimson-strong">{{ $member->position }}</p>
                            @endif
                            @if ($member->job_description)
                                <p class="mt-1 text-sm whitespace-pre-line text-muted-foreground">{{ $member->job_description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="bg-brand-crimson-strong text-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-4 py-12 sm:px-6">
            <div>
                <p class="font-medium text-white/85">Become a member</p>
                <h2 class="mt-1 max-w-2xl font-display text-3xl font-semibold tracking-tight">Join Our Community of Research Professionals to Stay Ahead</h2>
            </div>
            <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center rounded-full bg-white px-7 font-semibold text-brand-navy hover:bg-brand-navy hover:text-white">Join Us</a>
        </div>
    </section>

    <section id="about-us" class="scroll-mt-6">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="font-display text-4xl font-semibold tracking-tight">About Us</h2>
            <p class="mt-3 max-w-3xl text-lg text-muted-foreground">OASIS Research Community was established to advance the practice of high-quality research by providing research training and support to research enthusiasts and professionals around the world, particularly from developing countries like Nigeria. OASIS creates and operates platforms that promote and optimize intellectual insights, research capabilities and research outcomes for our members and corporate clients.</p>
            <dl class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                @foreach ([
                    'Our Vision' => 'To sustainably optimize academic and professional research outcomes for stakeholders',
                    'Our Mission' => 'To entrench global research best practices in the academic and professional research community through research education, support, publishing, and marketing',
                    'Our Core Values' => 'Excellence. Ethics. Integrity.',
                    'Our Recognition' => 'OASIS Research Community is registered in Nigeria with the Corporate Affairs Commission (CAC) as a Company Limited by Guarantee with RC No:2008295 and authorization of the Attorney General of the Federation, the Federal Ministry of Education and the National Board of Technical Education (NBTE).',
                ] as $term => $text)
                    <div class="border-t-2 border-brand-navy pt-4">
                        <dt class="font-display text-2xl font-semibold">{{ $term }}</dt>
                        <dd class="mt-2 text-muted-foreground">{{ $text }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section id="contact-us" class="scroll-mt-6 bg-brand-paper">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="font-display text-4xl font-semibold tracking-tight">Contact Us</h2>
            <div class="mt-8 grid gap-8 md:grid-cols-3">
                <div>
                    <h3 class="font-display text-xl font-semibold">Send Us Email</h3>
                    <a href="mailto:{{ config('oasis.support_email') }}" class="mt-2 inline-block font-medium break-all underline hover:text-brand-crimson-strong">{{ config('oasis.support_email') }}</a>
                </div>
                <div>
                    <h3 class="font-display text-xl font-semibold">Let's Speak</h3>
                    @foreach (config('oasis.phones') as $phone)
                        <a href="tel:{{ str_replace(' ', '', $phone) }}" class="mt-2 block font-medium hover:text-brand-crimson-strong">{{ $phone }}</a>
                    @endforeach
                </div>
                <div>
                    <h3 class="font-display text-xl font-semibold">{{ config('oasis.address.0') }}</h3>
                    <address class="mt-2 text-muted-foreground not-italic">
                        {{ config('oasis.address.1') }}<br>{{ config('oasis.address.2') }}
                    </address>
                </div>
            </div>
            <iframe src="{{ config('oasis.map_embed_url') }}" title="Map showing the National Secretariat, 11 Banjul Street, Wuse 2, Abuja" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen class="mt-10 h-80 w-full rounded-2xl border-0"></iframe>
        </div>
    </section>
@endsection
