import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronDown, Clock, ExternalLink, FileText, Play } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { naira } from '@/components/membership/form-bits';
import { NextStep, onNavy } from '@/components/membership/next-step';
import { JourneyTimeline } from '@/components/membership/timeline';
import type { JourneyStep } from '@/components/membership/timeline';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { account, exam, show } from '@/routes/membership';
import { payAgain, sit, start } from '@/routes/membership/exam';

type Attempt = {
    id: number;
    number: number;
    grade: string;
    date: string | null;
    percent: number | null;
    correct: number | null;
    total: number;
    passed: boolean | null;
    areas: { label: string; correct: number; total: number }[];
};

type Material = {
    id: number;
    title: string;
    area: string;
    description: string | null;
    url: string;
    is_file: boolean;
};

type Props = {
    grade: string | null;
    fee: { amount: number; paid: boolean } | null;
    attemptsOnFee: number;
    attemptsPerFee: number;
    running: { id: number; ends_at: string } | null;
    canStart: boolean;
    waitUntil: string | null;
    bankReady: boolean;
    needsNewFee: boolean;
    needsExam: boolean;
    bandOffer: string | null;
    passed: boolean;
    rules: { questions: number; minutes: number; pass: number };
    attempts: Attempt[];
    materials: Material[] | null;
    journey: JourneyStep[];
};

function StartDialog({
    props,
    onClose,
}: {
    props: Props;
    onClose: () => void;
}) {
    const form = useForm({});

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Start attempt {props.attemptsOnFee + 1} of{' '}
                        {props.attemptsPerFee}?
                    </DialogTitle>
                    <DialogDescription>
                        The clock starts now and keeps running even if you leave
                        the page.
                    </DialogDescription>
                </DialogHeader>
                <ul className="grid list-disc gap-1.5 pl-5 text-sm">
                    <li>
                        {props.rules.questions} questions, {props.rules.minutes}{' '}
                        minutes. You need {props.rules.pass}% to pass.
                    </li>
                    <li>
                        Answers save as you go. If your connection drops, come
                        back to this page and carry on.
                    </li>
                    <li>
                        When the time is up, your answers are marked as they
                        are.
                    </li>
                    <li>
                        Afterwards you see your score and how you did in each
                        area, but not the answers.
                    </li>
                </ul>
                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Not yet
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={() => form.post(start.url())}
                    >
                        <Play />
                        Start now
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Each area's share right, weakest first; ties keep the bigger area first. */
function byWeakest(areas: Attempt['areas']) {
    const share = (area: Attempt['areas'][number]) =>
        area.total ? area.correct / area.total : 0;

    return [...areas].sort((a, b) => share(a) - share(b) || b.total - a.total);
}

function AreaBars({ areas, pass }: { areas: Attempt['areas']; pass: number }) {
    return (
        <ul className="grid gap-x-8 gap-y-3 @3xl:grid-cols-2">
            {areas.map((area) => {
                const share = area.total ? area.correct / area.total : 0;
                const below = share * 100 < pass;

                return (
                    <li key={area.label} className="text-sm">
                        <div className="flex justify-between gap-3">
                            <span className={cn(below && 'font-semibold')}>
                                {area.label}
                            </span>
                            <span className="shrink-0 text-muted-foreground">
                                {area.correct} of {area.total}
                            </span>
                        </div>
                        <span
                            aria-hidden
                            className="mt-1 block h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                className={cn(
                                    'block h-full rounded-full',
                                    below
                                        ? 'bg-brand-crimson-strong dark:bg-brand-crimson'
                                        : 'bg-brand-navy dark:bg-brand-on-dark',
                                )}
                                style={{ width: `${share * 100}%` }}
                            />
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

/** The latest attempt: the score large against the pass line, then the areas. */
function ScoreCard({
    attempt,
    pass,
    attemptsOnFee,
    attemptsPerFee,
}: {
    attempt: Attempt;
    pass: number;
    attemptsOnFee: number;
    attemptsPerFee: number;
}) {
    const percent = attempt.percent ?? 0;
    const sorted = byWeakest(attempt.areas);
    const weak = sorted.filter(
        (area) => (area.total ? area.correct / area.total : 0) * 100 < pass,
    );
    const strong = sorted.filter((area) => !weak.includes(area));

    return (
        <section
            aria-labelledby="score-heading"
            className="@container rounded-card border border-border bg-background p-6 md:p-8"
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2
                        id="score-heading"
                        className="text-heading03 font-semibold"
                    >
                        Attempt {attempt.number}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {attempt.date}, {attempt.grade} exam
                    </p>
                </div>
                <div
                    className="flex items-center gap-1.5"
                    aria-label={`${attemptsOnFee} of ${attemptsPerFee} attempts used on this exam fee`}
                    role="img"
                >
                    {Array.from({ length: attemptsPerFee }, (_, i) => (
                        <span
                            key={i}
                            className={cn(
                                'size-3 rounded-full',
                                i < attemptsOnFee
                                    ? 'bg-brand-navy dark:bg-brand-on-dark'
                                    : 'ring-2 ring-brand-line ring-inset dark:ring-white/25',
                            )}
                        />
                    ))}
                    <span className="ml-1.5 text-sm text-muted-foreground">
                        {attemptsOnFee} of {attemptsPerFee} used
                    </span>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <p className="font-display text-heading01 leading-none font-black">
                    {percent}%
                </p>
                <p
                    className={cn(
                        'font-semibold',
                        attempt.passed
                            ? 'text-brand-navy dark:text-brand-on-dark'
                            : 'text-brand-crimson-strong dark:text-brand-crimson',
                    )}
                >
                    {attempt.passed ? 'Passed' : `Below the ${pass}% pass mark`}
                </p>
                <p className="text-muted-foreground">
                    {attempt.correct} of {attempt.total} right
                </p>
            </div>

            {/* The score against the pass mark. */}
            <div aria-hidden className="relative mt-4 pb-6">
                <div className="h-3 overflow-hidden rounded-full bg-muted">
                    <div
                        className={cn(
                            'h-full rounded-full',
                            attempt.passed
                                ? 'bg-brand-navy dark:bg-brand-on-dark'
                                : 'bg-brand-crimson-strong dark:bg-brand-crimson',
                        )}
                        style={{ width: `${percent}%` }}
                    />
                </div>
                <div
                    className="absolute top-[-4px] h-5 w-0.5 rounded-full bg-foreground"
                    style={{ left: `${pass}%` }}
                />
                <span
                    className="absolute top-5 -translate-x-1/2 text-xs font-semibold"
                    style={{ left: `${pass}%` }}
                >
                    {pass}%
                </span>
            </div>

            {weak.length > 0 && (
                <div className="mt-4 border-t border-border pt-6">
                    <h3 className="font-semibold">
                        {attempt.passed
                            ? `Areas under ${pass}%`
                            : 'Work on these first'}
                    </h3>
                    {!attempt.passed && (
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Areas where you scored under {pass}%, weakest first.
                        </p>
                    )}
                    <div className="mt-4">
                        <AreaBars areas={weak} pass={pass} />
                    </div>
                </div>
            )}
            {strong.length > 0 && (
                <details
                    className="group mt-6 border-t border-border pt-4"
                    open={weak.length === 0}
                >
                    <summary className="flex cursor-pointer list-none items-center gap-2 rounded-control font-semibold focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                        <ChevronDown
                            aria-hidden
                            className="size-4 transition-transform group-open:rotate-180"
                        />
                        Areas at {pass}% or more ({strong.length})
                    </summary>
                    <div className="mt-4">
                        <AreaBars areas={strong} pass={pass} />
                    </div>
                </details>
            )}
        </section>
    );
}

/** Earlier attempts, folded away under the latest one. */
function EarlierAttempts({
    attempts,
    pass,
}: {
    attempts: Attempt[];
    pass: number;
}) {
    return (
        <details className="group @container rounded-card border border-border bg-background">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 rounded-card p-5 font-semibold focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none md:px-6 [&::-webkit-details-marker]:hidden">
                Earlier attempts ({attempts.length})
                <ChevronDown
                    aria-hidden
                    className="size-4 transition-transform group-open:rotate-180"
                />
            </summary>
            <ul className="divide-y divide-border border-t border-border">
                {attempts.map((attempt) => (
                    <li key={attempt.id} className="p-5 md:px-6">
                        <p className="flex flex-wrap items-baseline justify-between gap-x-4">
                            <span className="font-semibold">
                                Attempt {attempt.number}: {attempt.percent}%
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {attempt.correct} of {attempt.total} right,{' '}
                                {attempt.date}
                            </span>
                        </p>
                        <div className="mt-4">
                            <AreaBars
                                areas={byWeakest(attempt.areas)}
                                pass={pass}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </details>
    );
}

/** Prep materials grouped by area; areas found hard in the last attempt come first. */
function PrepMaterials({
    materials,
    weakAreas,
}: {
    materials: Material[] | null;
    weakAreas: string[];
}) {
    const groups = new Map<string, Material[]>();
    for (const material of materials ?? []) {
        groups.set(material.area, [
            ...(groups.get(material.area) ?? []),
            material,
        ]);
    }
    const ordered = [...groups.entries()].sort(
        ([a], [b]) =>
            Number(weakAreas.includes(b)) - Number(weakAreas.includes(a)),
    );

    return (
        <section
            aria-labelledby="prep-heading"
            className="rounded-card border border-border bg-background"
        >
            <h2
                id="prep-heading"
                className="border-b border-border p-5 text-heading03 font-semibold md:px-6"
            >
                Prep materials
            </h2>
            {materials === null ? (
                <p className="flex items-center gap-2 p-5 text-muted-foreground md:px-6">
                    <Clock aria-hidden className="size-4" />
                    They open once your exam fee is paid.
                </p>
            ) : materials.length === 0 ? (
                <p className="p-5 text-muted-foreground md:px-6">
                    OASIS is adding the prep materials. Check back soon.
                </p>
            ) : (
                <div className="divide-y divide-border">
                    {ordered.map(([area, items]) => (
                        <div key={area} className="p-5 md:px-6">
                            <h3 className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                {area}
                                {weakAreas.includes(area) && (
                                    <span className="rounded-full bg-brand-crimson-strong px-2 py-0.5 text-xs text-white dark:bg-brand-crimson">
                                        Work on this
                                    </span>
                                )}
                            </h3>
                            <ul className="mt-3 grid gap-3">
                                {items.map((material) => (
                                    <li
                                        key={material.id}
                                        className="flex items-start gap-3"
                                    >
                                        {material.is_file ? (
                                            <FileText
                                                aria-hidden
                                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                            />
                                        ) : (
                                            <ExternalLink
                                                aria-hidden
                                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                            />
                                        )}
                                        <div className="min-w-0">
                                            <a
                                                href={material.url}
                                                target="_blank"
                                                rel="noopener"
                                                className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                            >
                                                {material.title}
                                                <span className="sr-only">
                                                    {' '}
                                                    (opens in a new tab)
                                                </span>
                                            </a>
                                            {material.description && (
                                                <p className="mt-0.5 max-w-prose text-sm text-muted-foreground">
                                                    {material.description}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function ExamRules({
    rules,
    attemptsPerFee,
}: {
    rules: Props['rules'];
    attemptsPerFee: number;
}) {
    const items = [
        ['Questions', `${rules.questions}`],
        ['Time', `${rules.minutes} minutes`],
        ['To pass', `${rules.pass}%`],
        ['Attempts', `${attemptsPerFee} on each exam fee`],
    ];

    return (
        <section
            aria-labelledby="rules-heading"
            className="rounded-card border border-border bg-background p-6"
        >
            <h2 id="rules-heading" className="text-heading03 font-semibold">
                Exam rules
            </h2>
            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                {items.map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <ul className="mt-4 grid gap-1.5 border-t border-border pt-4 text-sm text-muted-foreground">
                <li>
                    Answers save as you go, and the clock keeps running if you
                    leave.
                </li>
                <li>
                    After {attemptsPerFee} attempts, pay the fee again; the next
                    opens 24 hours after your last.
                </li>
                <li>You see your score by area, not the answers.</li>
            </ul>
        </section>
    );
}

/** What to do now, at every stage of the exam. */
function ExamNextStep({
    props,
    onStart,
}: {
    props: Props;
    onStart: () => void;
}) {
    const payAgainForm = useForm({});
    const left = props.attemptsPerFee - props.attemptsOnFee;
    const paid = props.fee?.paid === true;

    if (props.passed) {
        return (
            <NextStep
                title="You passed"
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={show()}>See my membership</Link>
                    </Button>
                }
            >
                <p>
                    You are admitted. Your induction fee, with the exam fee
                    taken off, is on your fees and payments page.
                </p>
            </NextStep>
        );
    }
    if (props.bandOffer) {
        return (
            <NextStep
                title={`Your score offers you ${props.bandOffer}`}
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={show()}>See my offer</Link>
                    </Button>
                }
            >
                <p>
                    Accept it on My membership to join as {props.bandOffer}, or
                    pay the exam fee again there and try for the grade you
                    applied for.
                </p>
            </NextStep>
        );
    }
    if (props.running) {
        return (
            <NextStep
                title="Your exam is in progress"
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={sit(props.running.id)}>
                            Carry on with the exam
                        </Link>
                    </Button>
                }
            >
                <p>The clock is running. Your answers so far are saved.</p>
            </NextStep>
        );
    }
    if (!paid) {
        return (
            <NextStep
                title={
                    props.fee
                        ? `Pay the exam fee of ${naira(props.fee.amount)}`
                        : 'Pay the exam fee to begin'
                }
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={account()}>Go to fees and payments</Link>
                    </Button>
                }
            >
                <p>
                    {props.fee
                        ? 'Once it is paid, the prep materials and the exam open here.'
                        : 'Your exam fee appears on your fees and payments page once you accept your grade offer.'}
                </p>
            </NextStep>
        );
    }
    if (props.needsNewFee) {
        return (
            <NextStep
                title={`You have used your ${props.attemptsPerFee} attempts`}
                actions={
                    <Button
                        size="lg"
                        disabled={payAgainForm.processing}
                        onClick={() => payAgainForm.post(payAgain.url())}
                        className={onNavy}
                    >
                        Pay the exam fee again
                    </Button>
                }
            >
                <p>
                    To try again, pay the exam fee again. Your next attempt
                    opens 24 hours after your last one.
                </p>
            </NextStep>
        );
    }
    if (!props.bankReady) {
        return (
            <NextStep title="The exam opens soon">
                <p>
                    OASIS is finishing the exam questions. Read the prep
                    materials meanwhile; we will email you when the exam opens.
                </p>
            </NextStep>
        );
    }
    if (props.waitUntil) {
        return (
            <NextStep title={`Your next attempt opens ${props.waitUntil}`}>
                <p>
                    There are 24 hours between your last attempt and the first
                    on a new exam fee. Use the time on the areas marked below.
                </p>
            </NextStep>
        );
    }

    return (
        <NextStep
            title={
                props.attemptsOnFee === 0
                    ? 'Take your exam'
                    : `Ready for attempt ${props.attemptsOnFee + 1}`
            }
            actions={
                <Button
                    size="lg"
                    disabled={!props.canStart}
                    onClick={onStart}
                    className={onNavy}
                >
                    <Play />
                    Start attempt {props.attemptsOnFee + 1}
                </Button>
            }
        >
            <p>
                {props.rules.questions} questions in {props.rules.minutes}{' '}
                minutes; {props.rules.pass}% to pass. You have {left}{' '}
                {left === 1 ? 'attempt' : 'attempts'} left on your exam fee.
                {props.attemptsOnFee > 0 &&
                    ' Go over the areas marked below first.'}
            </p>
        </NextStep>
    );
}

export default function MembershipExam(props: Props) {
    const [starting, setStarting] = useState(false);
    const [latest, ...earlier] = props.attempts;
    const weakAreas = latest
        ? latest.areas
              .filter(
                  (area) =>
                      (area.total ? area.correct / area.total : 0) * 100 <
                      props.rules.pass,
              )
              .map((area) => area.label)
        : [];

    return (
        <>
            <Head title="My exam" />
            <div className="@container w-full max-w-7xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="My exam"
                    description={
                        (latest?.grade ?? props.grade)
                            ? `The ${latest?.grade ?? props.grade} membership exam.`
                            : 'The membership exam.'
                    }
                />

                <div className="grid items-start gap-6 @4xl:grid-cols-[minmax(0,1fr)_20rem] @4xl:gap-10 @6xl:grid-cols-[minmax(0,1fr)_23rem]">
                    <div className="grid min-w-0 gap-6">
                        <ExamNextStep
                            props={props}
                            onStart={() => setStarting(true)}
                        />
                        {latest && (
                            <ScoreCard
                                attempt={latest}
                                pass={props.rules.pass}
                                attemptsOnFee={props.attemptsOnFee}
                                attemptsPerFee={props.attemptsPerFee}
                            />
                        )}
                        {earlier.length > 0 && (
                            <EarlierAttempts
                                attempts={earlier}
                                pass={props.rules.pass}
                            />
                        )}
                        <PrepMaterials
                            materials={props.materials}
                            weakAreas={props.passed ? [] : weakAreas}
                        />
                    </div>

                    <aside className="grid gap-6 @4xl:sticky @4xl:top-6">
                        <ExamRules
                            rules={props.rules}
                            attemptsPerFee={props.attemptsPerFee}
                        />
                        {props.journey.length > 0 && (
                            <div className="rounded-card border border-border bg-background p-6">
                                <JourneyTimeline steps={props.journey} />
                            </div>
                        )}
                    </aside>
                </div>
            </div>

            {starting && (
                <StartDialog props={props} onClose={() => setStarting(false)} />
            )}
        </>
    );
}

MembershipExam.layout = {
    breadcrumbs: [{ title: 'My exam', href: exam() }],
};
