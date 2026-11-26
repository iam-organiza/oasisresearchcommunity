import { Head, Link, useForm } from '@inertiajs/react';
import { Clock, ExternalLink, FileText, Play } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Journey } from '@/components/membership/journey';
import { naira } from '@/components/membership/form-bits';
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
    passed: boolean;
    rules: { questions: number; minutes: number; pass: number };
    attempts: Attempt[];
    materials: Material[] | null;
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

function AttemptResult({ attempt, pass }: { attempt: Attempt; pass: number }) {
    return (
        <li className="p-4 md:px-6 md:py-5">
            <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <p className="font-semibold">
                    Attempt {attempt.number}: {attempt.percent}%
                    <span
                        className={cn(
                            'ml-3 rounded-full px-2.5 py-0.5 text-sm',
                            attempt.passed
                                ? 'bg-primary text-primary-foreground'
                                : 'ring-1 ring-border ring-inset',
                        )}
                    >
                        {attempt.passed ? 'Passed' : `Below ${pass}%`}
                    </span>
                </p>
                <p className="text-sm text-muted-foreground">
                    {attempt.grade} · {attempt.correct} of {attempt.total} right
                    · {attempt.date}
                </p>
            </div>
            <ul className="mt-4 grid gap-x-8 gap-y-2 @3xl:grid-cols-2">
                {attempt.areas.map((area) => {
                    const share = area.total ? area.correct / area.total : 0;

                    return (
                        <li key={area.label} className="text-sm">
                            <div className="flex justify-between gap-3">
                                <span>{area.label}</span>
                                <span className="text-muted-foreground">
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
                                        share * 100 >= pass
                                            ? 'bg-primary'
                                            : 'bg-brand-crimson-strong dark:bg-brand-crimson',
                                    )}
                                    style={{ width: `${share * 100}%` }}
                                />
                            </span>
                        </li>
                    );
                })}
            </ul>
        </li>
    );
}

export default function MembershipExam(props: Props) {
    const [starting, setStarting] = useState(false);
    const payAgainForm = useForm({});
    const left = props.attemptsPerFee - props.attemptsOnFee;
    const paid = props.fee?.paid === true;

    return (
        <>
            <Head title="My exam" />
            <div className="@container max-w-6xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="My exam"
                    description={
                        props.grade
                            ? `The ${props.grade} membership exam.`
                            : 'The membership exam.'
                    }
                />
                <Journey current={props.passed ? 4 : paid ? 3 : 2} />

                <div className="grid gap-6">
                    <section className="rounded-card bg-brand-navy p-6 text-white md:p-8 dark:bg-brand-night-raised">
                        {props.passed ? (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    You passed
                                </h2>
                                <p className="mt-2 max-w-prose text-brand-on-dark">
                                    You are admitted. Your induction fee, with
                                    the exam fee taken off, is on your fees and
                                    payments page.
                                </p>
                                <Button
                                    asChild
                                    size="lg"
                                    className="mt-6 bg-white text-brand-navy hover:bg-white/90"
                                >
                                    <Link href={show()}>See my membership</Link>
                                </Button>
                            </>
                        ) : props.running ? (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    Your exam is in progress
                                </h2>
                                <p className="mt-2 text-brand-on-dark">
                                    The clock is running. Your answers so far
                                    are saved.
                                </p>
                                <Button
                                    asChild
                                    size="lg"
                                    className="mt-6 bg-white text-brand-navy hover:bg-white/90"
                                >
                                    <Link href={sit(props.running.id)}>
                                        Carry on with the exam
                                    </Link>
                                </Button>
                            </>
                        ) : !paid ? (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    Pay the exam fee to begin
                                </h2>
                                <p className="mt-2 max-w-prose text-brand-on-dark">
                                    {props.fee
                                        ? `The exam fee of ${naira(props.fee.amount)} is on your fees and payments page. Once it is paid, the prep materials and the exam open here.`
                                        : 'Your exam fee appears on your fees and payments page once you accept your grade offer.'}
                                </p>
                                <Button
                                    asChild
                                    size="lg"
                                    className="mt-6 bg-white text-brand-navy hover:bg-white/90"
                                >
                                    <Link href={account()}>
                                        Go to fees and payments
                                    </Link>
                                </Button>
                            </>
                        ) : props.needsNewFee ? (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    You have used your {props.attemptsPerFee}{' '}
                                    attempts
                                </h2>
                                <p className="mt-2 max-w-prose text-brand-on-dark">
                                    To try again, pay the exam fee again. Your
                                    next attempt opens 24 hours after your last
                                    one.
                                </p>
                                <Button
                                    size="lg"
                                    disabled={payAgainForm.processing}
                                    onClick={() =>
                                        payAgainForm.post(payAgain.url())
                                    }
                                    className="mt-6 bg-white text-brand-navy hover:bg-white/90"
                                >
                                    Pay the exam fee again
                                </Button>
                            </>
                        ) : !props.bankReady ? (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    The exam opens soon
                                </h2>
                                <p className="mt-2 max-w-prose text-brand-on-dark">
                                    OASIS is finishing the exam questions. Read
                                    the prep materials meanwhile; we will email
                                    you when the exam opens.
                                </p>
                            </>
                        ) : props.waitUntil ? (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    Your next attempt opens {props.waitUntil}
                                </h2>
                                <p className="mt-2 max-w-prose text-brand-on-dark">
                                    There are 24 hours between your last attempt
                                    and the first on a new exam fee.
                                </p>
                            </>
                        ) : (
                            <>
                                <h2 className="text-heading02 font-bold">
                                    Ready when you are
                                </h2>
                                <p className="mt-2 max-w-prose text-brand-on-dark">
                                    {props.rules.questions} questions in{' '}
                                    {props.rules.minutes} minutes;{' '}
                                    {props.rules.pass}% to pass. You have {left}{' '}
                                    {left === 1 ? 'attempt' : 'attempts'} left
                                    on your exam fee.
                                </p>
                                <Button
                                    size="lg"
                                    disabled={!props.canStart}
                                    onClick={() => setStarting(true)}
                                    className="mt-6 bg-white text-brand-navy hover:bg-white/90"
                                >
                                    <Play />
                                    Start attempt {props.attemptsOnFee + 1}
                                </Button>
                            </>
                        )}
                    </section>

                    {props.attempts.length > 0 && (
                        <section
                            aria-labelledby="results-heading"
                            className="@container rounded-card border border-border bg-background"
                        >
                            <h2
                                id="results-heading"
                                className="border-b border-border p-4 font-semibold md:px-6"
                            >
                                Your results
                            </h2>
                            <ul className="divide-y divide-border">
                                {props.attempts.map((attempt) => (
                                    <AttemptResult
                                        key={attempt.id}
                                        attempt={attempt}
                                        pass={props.rules.pass}
                                    />
                                ))}
                            </ul>
                        </section>
                    )}

                    <section
                        aria-labelledby="prep-heading"
                        className="rounded-card border border-border bg-background"
                    >
                        <h2
                            id="prep-heading"
                            className="border-b border-border p-4 font-semibold md:px-6"
                        >
                            Prep materials
                        </h2>
                        {props.materials === null ? (
                            <p className="flex items-center gap-2 p-4 text-muted-foreground md:px-6">
                                <Clock aria-hidden className="size-4" />
                                They open once your exam fee is paid.
                            </p>
                        ) : props.materials.length === 0 ? (
                            <p className="p-4 text-muted-foreground md:px-6">
                                OASIS is adding the prep materials. Check back
                                soon.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {props.materials.map((material) => (
                                    <li
                                        key={material.id}
                                        className="flex items-start gap-3 p-4 md:px-6"
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
                                            </a>
                                            <p className="text-sm text-muted-foreground">
                                                {material.area}
                                            </p>
                                            {material.description && (
                                                <p className="mt-1 max-w-prose text-sm">
                                                    {material.description}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
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
