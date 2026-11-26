<?php

namespace App\Membership;

use App\Enums\Account\ChargeKind;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Route;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\User;

/**
 * The way to membership as a timeline for My membership: Apply, Grade
 * offer, Exam fee, Exam, Induction fee, Member. Each step is done, current,
 * upcoming or skipped (no exam on their route), with a short detail such as
 * a date, an amount or a score. Members who joined before applications were
 * online have no timeline.
 */
final class Journey
{
    /**
     * @return list<array{key: string, label: string, state: string, detail: string|null}>
     */
    public static function for(User $user, ?MembershipApplication $application, ?Membership $membership): array
    {
        if ($application === null && $membership !== null) {
            return [];
        }

        $charges = Charge::query()->with('payments')->where('user_id', $user->id)->whereNull('cancelled_at')->orderByDesc('id')->get();
        $examFee = $charges->firstWhere('kind', ChargeKind::Exam);
        $induction = $charges->firstWhere('kind', ChargeKind::Induction);
        $status = $application?->status;
        $active = $membership?->activated_at !== null;
        $examStatus = $application?->exam_status;

        // No exam on this route, or passed before applying (no exam fee charged).
        $examSkipped = $application !== null && $examFee === null && (
            $application->route !== Route::Exam || $examStatus === 'passed'
        );
        $skipNote = $application?->route === Route::Exam ? 'Passed before you applied' : 'Not needed on your route';
        $examDone = $membership !== null || in_array($examStatus, ['passed', 'band'], true);
        $examFeeSettled = $examFee?->isSettled() === true;
        $offerTaken = $membership !== null || in_array($status, [ApplicationStatus::Accepted, ApplicationStatus::Approved], true)
            || ($examStatus === 'band' && $examFee !== null);
        $applying = $application === null || in_array($status, [ApplicationStatus::Draft, ApplicationStatus::MoreInformation], true);

        $steps = [
            self::step('apply', 'Apply', match (true) {
                ! $applying => 'done',
                default => 'current',
            }, match (true) {
                $application === null => 'About 15 minutes',
                $status === ApplicationStatus::MoreInformation => 'The Registrar asked for more',
                $applying => self::stepsLeft($application),
                default => $application->submitted_at ? 'Submitted '.$application->submitted_at->format('j M Y') : null,
            }),
            self::step('offer', 'Grade offer', match (true) {
                $offerTaken => 'done',
                $applying => 'upcoming',
                default => 'current',
            }, match (true) {
                $status === ApplicationStatus::Declined => 'Not admitted this time',
                $status === ApplicationStatus::Submitted => 'Your appeal is with the Registrar',
                $offerTaken && $membership !== null => $membership->grade->label(),
                $offerTaken && $application?->offered_grade !== null && $examStatus !== 'band' => $application->offered_grade->label().', accepted',
                $application?->offered_grade !== null && $status === ApplicationStatus::Offered && $examStatus !== 'band' => $application->offered_grade->label().' offered',
                $examStatus === 'band' => 'Accepted, then a new offer',
                default => 'Straight after you submit',
            }),
            self::step('exam_fee', 'Exam fee', match (true) {
                $examSkipped => 'skipped',
                $examFeeSettled || $examDone => 'done',
                $offerTaken => 'current',
                default => 'upcoming',
            }, match (true) {
                $examSkipped => 'Not needed',
                $examFeeSettled => self::settledOn($examFee),
                $examFee !== null => '₦'.number_format($examFee->balance()).' to pay',
                default => 'Opens the prep materials',
            }),
            self::step('exam', 'Exam', match (true) {
                $examSkipped => 'skipped',
                $examStatus === 'band' && $membership === null => 'current',
                $examDone => 'done',
                $examFeeSettled => 'current',
                default => 'upcoming',
            }, match (true) {
                $examSkipped => $skipNote,
                $examStatus === 'band' => 'Best score '.self::percent($application->exam_best_percent).', see your new offer',
                $examDone && $application?->exam_best_percent !== null => 'Passed with '.self::percent($application->exam_best_percent),
                $examDone => 'Passed',
                $application?->exam_best_percent !== null => 'Best so far '.self::percent($application->exam_best_percent),
                default => '50 questions in 60 minutes',
            }),
            self::step('induction_fee', 'Induction fee', match (true) {
                $active => 'done',
                $membership !== null => 'current',
                default => 'upcoming',
            }, match (true) {
                $active && $induction !== null => self::settledOn($induction),
                $induction !== null => '₦'.number_format($induction->balance()).' to pay',
                default => 'Your number follows',
            }),
            self::step('member', 'Member', $active ? 'done' : 'upcoming', $membership?->activated_at !== null
                ? 'Since '.$membership->activated_at->format('j M Y')
                : 'Number and certificate'),
        ];

        return $steps;
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null}
     */
    private static function step(string $key, string $label, string $state, ?string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail];
    }

    private static function stepsLeft(MembershipApplication $application): string
    {
        $left = count($application->missingSteps());

        return $left === 0 ? 'Ready to submit' : ($left === 1 ? '1 part left' : "{$left} parts left");
    }

    private static function settledOn(Charge $charge): string
    {
        if ($charge->waived_amount >= $charge->amount) {
            return 'Waived';
        }
        $date = $charge->payments->whereNull('voided_at')->max('paid_on');

        return $date ? 'Paid '.$date->format('j M Y') : 'Paid';
    }

    private static function percent(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
    }
}
