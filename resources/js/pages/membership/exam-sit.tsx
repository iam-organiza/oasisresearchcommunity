import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Clock } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
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
import { answer, finish } from '@/routes/membership/exam';

type Question = {
    position: number;
    scenario: string | null;
    question: string;
    options: string[];
    answer: number | null;
};

const letter = (i: number) => String.fromCharCode(65 + i);

const clock = (seconds: number) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
};

/**
 * One question at a time, with a map of all of them. The clock counts down
 * from the server's time left; each answer is saved as it is chosen, and
 * the attempt is marked when the time runs out.
 */
export default function ExamSit({
    attempt,
    questions,
}: {
    attempt: {
        id: number;
        number: number;
        grade: string;
        seconds_left: number;
    };
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
    const finished = useRef(false);
    const deadline = useRef(Date.now() + attempt.seconds_left * 1000);

    const finishNow = useCallback(() => {
        if (finished.current) {
            return;
        }
        finished.current = true;
        router.post(finish.url(attempt.id));
    }, [attempt.id]);

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

    const choose = (option: number) => {
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
    };

    return (
        <>
            <Head title={`Exam, question ${current + 1}`} />
            <div className="@container mx-auto grid w-full max-w-4xl gap-6 px-4 py-8 md:px-8 md:py-10">
                <div className="sticky top-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
                    <p className="font-semibold">
                        Question {current + 1} of {questions.length}
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            {attempt.grade} exam · attempt {attempt.number}
                        </span>
                    </p>
                    <p
                        role="timer"
                        aria-live={left <= 300 ? 'polite' : 'off'}
                        className={cn(
                            'flex items-center gap-2 rounded-full px-3 py-1 font-semibold tabular-nums',
                            left <= 300
                                ? 'bg-brand-crimson-strong text-white dark:bg-brand-crimson'
                                : 'bg-muted',
                        )}
                    >
                        <Clock aria-hidden className="size-4" />
                        {clock(left)} left
                    </p>
                </div>

                <section aria-labelledby="question-text" className="grid gap-5">
                    {question.scenario && (
                        <p className="max-w-prose border-l-2 border-border pl-4 whitespace-pre-line text-muted-foreground">
                            {question.scenario}
                        </p>
                    )}
                    <h1
                        id="question-text"
                        className="max-w-prose text-heading03 font-semibold whitespace-pre-line"
                    >
                        {question.question}
                    </h1>
                    <fieldset>
                        <legend className="sr-only">Choose one answer</legend>
                        <div className="grid gap-3">
                            {question.options.map((option, i) => {
                                const chosen = answers[question.position] === i;

                                return (
                                    <label
                                        key={`${question.position}-${i}`}
                                        className={cn(
                                            'flex cursor-pointer items-start gap-4 rounded-control border p-4 transition-colors',
                                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                            chosen
                                                ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
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
                                                'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
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
                                        <span className="pt-0.5">
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
                            'text-sm',
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

                <div className="flex justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        disabled={current === 0}
                        onClick={() => setCurrent((i) => i - 1)}
                    >
                        <ArrowLeft />
                        Previous
                    </Button>
                    {current < questions.length - 1 ? (
                        <Button
                            type="button"
                            size="lg"
                            onClick={() => setCurrent((i) => i + 1)}
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

                <nav
                    aria-label="All questions"
                    className="rounded-card border border-border bg-background p-4 md:p-6"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="font-semibold">All questions</h2>
                        <p className="text-sm text-muted-foreground">
                            {answered} of {questions.length} answered
                        </p>
                    </div>
                    <ol className="mt-3 grid grid-cols-[repeat(auto-fill,minmax(2.5rem,1fr))] gap-2">
                        {questions.map((item, i) => {
                            const done = answers[item.position] !== undefined;

                            return (
                                <li key={item.position}>
                                    <button
                                        type="button"
                                        onClick={() => setCurrent(i)}
                                        aria-current={
                                            i === current ? 'step' : undefined
                                        }
                                        className={cn(
                                            'flex h-10 w-full items-center justify-center rounded-control text-sm font-semibold tabular-nums',
                                            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                            done
                                                ? 'bg-primary text-primary-foreground'
                                                : 'ring-1 ring-border ring-inset hover:ring-foreground/40',
                                            i === current &&
                                                'outline-2 outline-offset-2 outline-brand-crimson-strong dark:outline-brand-crimson',
                                        )}
                                    >
                                        {i + 1}
                                        <span className="sr-only">
                                            {done
                                                ? ', answered'
                                                : ', not answered'}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                    <div className="mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirming(true)}
                        >
                            Finish the exam
                        </Button>
                    </div>
                </nav>
            </div>

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

ExamSit.layout = {
    breadcrumbs: [{ title: 'Exam', href: '#' }],
};
