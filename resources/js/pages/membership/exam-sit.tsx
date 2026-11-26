import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Clock,
    Flag,
    Grid3x3,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import OasisMark from '@/components/oasis-mark';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { answer, finish } from '@/routes/membership/exam';

type Question = {
    position: number;
    scenario: string | null;
    question: string;
    options: string[];
    answer: number | null;
};

type Attempt = {
    id: number;
    number: number;
    grade: string;
    seconds_left: number;
    seconds_total: number;
};

const letter = (i: number) => String.fromCharCode(65 + i);

const clock = (seconds: number) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
};

/** Five minutes or less: the timer turns crimson and is read out. */
const LAST_MINUTES = 300;

/**
 * Questions marked to review are only a reminder for the person sitting
 * the exam, so they stay in this browser and never reach the server.
 */
function useReviewMarks(attemptId: number) {
    const key = `exam-${attemptId}-review`;
    const [marked, setMarked] = useState<number[]>(() => {
        try {
            const saved = window.localStorage.getItem(key);

            return saved ? (JSON.parse(saved) as number[]) : [];
        } catch {
            return [];
        }
    });

    const toggle = (position: number) =>
        setMarked((previous) => {
            const next = previous.includes(position)
                ? previous.filter((item) => item !== position)
                : [...previous, position];
            try {
                window.localStorage.setItem(key, JSON.stringify(next));
            } catch {
                // Private windows can refuse storage; the marks still work for this visit.
            }

            return next;
        });

    /** The attempt is over; its marks are no use any more. */
    const forget = useCallback(() => {
        try {
            window.localStorage.removeItem(key);
        } catch {
            // Nothing was stored.
        }
    }, [key]);

    return { marked, toggle, forget };
}

/** Every question as a numbered square: answered, current and marked to review. */
function QuestionMap({
    questions,
    answers,
    marked,
    current,
    onGo,
}: {
    questions: Question[];
    answers: Record<number, number>;
    marked: number[];
    current: number;
    onGo: (index: number) => void;
}) {
    return (
        <>
            <ol className="grid grid-cols-[repeat(auto-fill,minmax(2.5rem,1fr))] gap-2">
                {questions.map((item, i) => {
                    const done = answers[item.position] !== undefined;
                    const flagged = marked.includes(item.position);
                    const isCurrent = i === current;

                    return (
                        <li key={item.position}>
                            <button
                                type="button"
                                onClick={() => onGo(i)}
                                aria-current={isCurrent ? 'step' : undefined}
                                className={cn(
                                    'relative flex h-10 w-full items-center justify-center rounded-control text-sm font-semibold',
                                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    done
                                        ? 'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy'
                                        : 'ring-1 ring-border ring-inset hover:ring-foreground/40',
                                    isCurrent &&
                                        'outline-2 outline-offset-2 outline-brand-crimson-strong dark:outline-brand-crimson',
                                )}
                            >
                                {i + 1}
                                {flagged && (
                                    <Flag
                                        aria-hidden
                                        className="absolute -top-1.5 -right-1.5 size-4 rounded-full bg-background fill-brand-crimson-strong p-0.5 text-brand-crimson-strong dark:fill-brand-crimson dark:text-brand-crimson"
                                    />
                                )}
                                <span className="sr-only">
                                    {done ? ', answered' : ', not answered'}
                                    {flagged ? ', marked to review' : ''}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
            <ul className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <li className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className="size-3 rounded-sm bg-brand-navy dark:bg-brand-on-dark"
                    />
                    Answered
                </li>
                <li className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className="size-3 rounded-sm ring-1 ring-border ring-inset"
                    />
                    Not answered
                </li>
                <li className="flex items-center gap-1.5">
                    <Flag
                        aria-hidden
                        className="size-3 fill-brand-crimson-strong text-brand-crimson-strong dark:fill-brand-crimson dark:text-brand-crimson"
                    />
                    To review
                </li>
            </ul>
        </>
    );
}

/**
 * The timed exam, full screen: one question at a time beside a map of all
 * of them. The clock counts down from the server's time left; each answer
 * is saved as it is chosen, and the attempt is marked when the time runs out.
 */
export default function ExamSit({
    attempt,
    questions,
}: {
    attempt: Attempt;
    questions: Question[];
}) {
    const [current, setCurrent] = useState(0);
    const [answers, setAnswers] = useState<Record<number, number>>(() =>
        Object.fromEntries(
            questions
                .filter((question) => question.answer !== null)
                .map((question) => [
                    question.position,
                    question.answer as number,
                ]),
        ),
    );
    const [saving, setSaving] = useState<'idle' | 'saving' | 'failed'>('idle');
    const [left, setLeft] = useState(attempt.seconds_left);
    const [confirming, setConfirming] = useState(false);
    const [mapOpen, setMapOpen] = useState(false);
    const { marked, toggle, forget } = useReviewMarks(attempt.id);
    const finished = useRef(false);
    const deadline = useRef(Date.now() + attempt.seconds_left * 1000);

    const finishNow = useCallback(() => {
        if (finished.current) {
            return;
        }
        finished.current = true;
        forget();
        router.post(finish.url(attempt.id));
    }, [attempt.id, forget]);

    useEffect(() => {
        const tick = window.setInterval(() => {
            const seconds = Math.max(
                0,
                Math.round((deadline.current - Date.now()) / 1000),
            );
            setLeft(seconds);
            if (seconds === 0) {
                window.clearInterval(tick);
                finishNow();
            }
        }, 1000);

        return () => window.clearInterval(tick);
    }, [finishNow]);

    const question = questions[current];
    const answered = Object.keys(answers).length;
    const unanswered = questions.length - answered;
    const isMarked = marked.includes(question.position);
    const lastMinutes = left <= LAST_MINUTES;

    const choose = useCallback(
        (option: number) => {
            setAnswers((previous) => ({
                ...previous,
                [question.position]: option,
            }));
            setSaving('saving');
            router.post(
                answer.url(attempt.id),
                { position: question.position, option },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['attempt'],
                    onSuccess: () => setSaving('idle'),
                    onError: () => setSaving('failed'),
                    onNetworkError: () => {
                        setSaving('failed');

                        return false;
                    },
                },
            );
        },
        [attempt.id, question.position],
    );

    const go = useCallback(
        (index: number) => {
            setCurrent(Math.min(questions.length - 1, Math.max(0, index)));
            setMapOpen(false);
            setSaving('idle');
            window.scrollTo({ top: 0 });
        },
        [questions.length],
    );

    // A to D answer the question; the arrow keys move between questions.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target =
                event.target instanceof Element ? event.target : null;
            if (
                event.altKey ||
                event.ctrlKey ||
                event.metaKey ||
                confirming ||
                mapOpen ||
                target?.closest('input, textarea, select, [role="dialog"]')
            ) {
                return;
            }
            const option = 'abcdefgh'.indexOf(event.key.toLowerCase());
            if (
                event.key.length === 1 &&
                option >= 0 &&
                option < question.options.length
            ) {
                event.preventDefault();
                choose(option);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                go(current + 1);
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(current - 1);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [choose, go, current, question.options.length, confirming, mapOpen]);

    const map = (
        <QuestionMap
            questions={questions}
            answers={answers}
            marked={marked}
            current={current}
            onGo={go}
        />
    );

    return (
        <>
            <Head title={`Exam, question ${current + 1}`} />
            <div className="@container min-h-dvh bg-brand-paper dark:bg-background">
                {/* The exam bar: who is sitting what, the clock, and the way out. */}
                <header className="sticky top-0 z-20 border-b border-border bg-background">
                    <div className="mx-auto flex w-full max-w-7xl items-center gap-3 px-4 py-3 md:px-8">
                        <OasisMark
                            ink="currentColor"
                            className="size-8 shrink-0 text-brand-navy dark:text-white"
                        />
                        <p className="min-w-0 flex-1 truncate font-semibold">
                            {attempt.grade} exam
                            <span className="font-normal text-muted-foreground">
                                , attempt {attempt.number}
                            </span>
                        </p>
                        <p
                            role="timer"
                            aria-live={lastMinutes ? 'polite' : 'off'}
                            className={cn(
                                'flex shrink-0 items-center gap-2 rounded-full px-3 py-1 font-semibold tabular-nums',
                                lastMinutes
                                    ? 'bg-brand-crimson-strong text-white dark:bg-brand-crimson'
                                    : 'bg-muted',
                            )}
                        >
                            <Clock aria-hidden className="size-4" />
                            {clock(left)}
                            <span className="sr-only @md:not-sr-only">
                                left
                            </span>
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            className="shrink-0 @5xl:hidden"
                            onClick={() => setMapOpen(true)}
                        >
                            <Grid3x3 />
                            <span className="sr-only @md:not-sr-only">
                                Questions
                            </span>{' '}
                            {answered}/{questions.length}
                        </Button>
                        <Button
                            type="button"
                            className="hidden shrink-0 @md:inline-flex"
                            onClick={() => setConfirming(true)}
                        >
                            Finish
                        </Button>
                    </div>
                    {/* Time left, as a line under the bar. */}
                    <div aria-hidden className="h-1 bg-muted">
                        <div
                            className={cn(
                                'h-full transition-[width] duration-1000 ease-linear',
                                lastMinutes
                                    ? 'bg-brand-crimson-strong dark:bg-brand-crimson'
                                    : 'bg-brand-navy dark:bg-brand-on-dark',
                            )}
                            style={{
                                width: `${(left / Math.max(1, attempt.seconds_total)) * 100}%`,
                            }}
                        />
                    </div>
                </header>

                <div className="mx-auto grid w-full max-w-7xl items-start gap-8 px-4 py-8 md:px-8 md:py-10 @5xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <main className="grid min-w-0 gap-6 rounded-card border border-border bg-background p-6 md:p-10">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="font-semibold text-muted-foreground">
                                Question {current + 1} of {questions.length}
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                aria-pressed={isMarked}
                                onClick={() => toggle(question.position)}
                                className={cn(
                                    isMarked &&
                                        'text-brand-crimson-strong hover:text-brand-crimson-strong dark:text-brand-crimson dark:hover:text-brand-crimson',
                                )}
                            >
                                <Flag
                                    className={cn(
                                        isMarked &&
                                            'fill-brand-crimson-strong dark:fill-brand-crimson',
                                    )}
                                />
                                {isMarked
                                    ? 'Marked to review'
                                    : 'Mark to review'}
                            </Button>
                        </div>

                        <section
                            aria-labelledby="question-text"
                            className="grid max-w-3xl gap-6"
                        >
                            {question.scenario && (
                                <p className="rounded-control bg-muted/70 p-4 whitespace-pre-line md:p-5">
                                    {question.scenario}
                                </p>
                            )}
                            <h1
                                id="question-text"
                                className="text-xl leading-snug font-semibold text-balance whitespace-pre-line md:text-2xl"
                            >
                                {question.question}
                            </h1>
                            <fieldset>
                                <legend className="sr-only">
                                    Choose one answer
                                </legend>
                                <div className="grid gap-3">
                                    {question.options.map((option, i) => {
                                        const chosen =
                                            answers[question.position] === i;

                                        return (
                                            <label
                                                key={`${question.position}-${i}`}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-4 rounded-control border p-4 transition-colors md:p-5',
                                                    'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                                    chosen
                                                        ? 'border-brand-crimson-strong bg-brand-crimson-strong/[0.03] ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:bg-brand-crimson/5 dark:ring-brand-crimson'
                                                        : 'border-border hover:border-foreground/40',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name={`question-${question.position}`}
                                                    checked={chosen}
                                                    onChange={() => choose(i)}
                                                    className="sr-only"
                                                />
                                                <span
                                                    aria-hidden
                                                    className={cn(
                                                        'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                                        chosen
                                                            ? 'bg-brand-crimson-strong text-white dark:bg-brand-crimson'
                                                            : 'ring-1 ring-border ring-inset',
                                                    )}
                                                >
                                                    {chosen ? (
                                                        <Check className="size-4" />
                                                    ) : (
                                                        letter(i)
                                                    )}
                                                </span>
                                                <span className="pt-1">
                                                    <span className="sr-only">
                                                        {letter(i)}.{' '}
                                                    </span>
                                                    {option}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>
                            <p
                                aria-live="polite"
                                className={cn(
                                    'min-h-5 text-sm',
                                    saving === 'failed'
                                        ? 'font-semibold text-destructive'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {saving === 'saving' && 'Saving…'}
                                {saving === 'failed' &&
                                    'That answer did not save. Check your connection and choose it again.'}
                                {saving === 'idle' &&
                                    answers[question.position] !== undefined &&
                                    'Saved.'}
                            </p>
                        </section>

                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-6">
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                disabled={current === 0}
                                onClick={() => go(current - 1)}
                            >
                                <ArrowLeft />
                                Previous
                            </Button>
                            <p className="hidden text-sm text-muted-foreground @3xl:block">
                                Keys: A to {letter(question.options.length - 1)}{' '}
                                to answer, ← and → to move
                            </p>
                            {current < questions.length - 1 ? (
                                <Button
                                    type="button"
                                    size="lg"
                                    onClick={() => go(current + 1)}
                                >
                                    Next
                                    <ArrowRight />
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="lg"
                                    onClick={() => setConfirming(true)}
                                >
                                    Finish the exam
                                </Button>
                            )}
                        </div>
                    </main>

                    {/* Wide screens: the map stays beside the question. */}
                    <aside
                        aria-label="All questions"
                        className="sticky top-24 hidden rounded-card border border-border bg-background p-6 @5xl:block"
                    >
                        <div className="flex items-baseline justify-between gap-2">
                            <h2 className="text-heading03 font-semibold">
                                Questions
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {answered} of {questions.length} answered
                            </p>
                        </div>
                        <div className="mt-4">{map}</div>
                        <Button
                            type="button"
                            className="mt-6 w-full"
                            onClick={() => setConfirming(true)}
                        >
                            Finish the exam
                        </Button>
                    </aside>
                </div>
            </div>

            {/* Narrower screens: the map opens from the bar. */}
            <Sheet open={mapOpen} onOpenChange={setMapOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[85dvh] overflow-y-auto"
                >
                    <SheetHeader>
                        <SheetTitle>Questions</SheetTitle>
                        <SheetDescription>
                            {answered} of {questions.length} answered
                            {marked.length > 0 &&
                                `, ${marked.length} marked to review`}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-6">
                        {map}
                        <Button
                            type="button"
                            className="mt-6 w-full"
                            onClick={() => {
                                setMapOpen(false);
                                setConfirming(true);
                            }}
                        >
                            Finish the exam
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

            {confirming && (
                <Dialog
                    open
                    onOpenChange={(open) => !open && setConfirming(false)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Finish the exam?</DialogTitle>
                            <DialogDescription>
                                {unanswered === 0
                                    ? 'You have answered every question. Your answers are marked straight away.'
                                    : `${unanswered} ${unanswered === 1 ? 'question is' : 'questions are'} not answered and will count as wrong. You still have ${clock(left)}.`}
                                {marked.length > 0 &&
                                    ` You marked ${marked.length} to review.`}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setConfirming(false)}
                            >
                                Keep going
                            </Button>
                            <Button type="button" onClick={finishNow}>
                                Finish and see my score
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
