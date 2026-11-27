<?php

namespace App\Account;

use App\Models\AccountEvent;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\User;

/**
 * A person's account as the Statement layout shows it: the totals, then
 * each charge with its payments and waiver under it. Shared by the member's
 * own page and the Registrar's.
 */
final class AccountStatement
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $user, bool $forStaff = false): array
    {
        $charges = $user->charges()->with(['payments.recordedBy:id,first_name,last_name', 'waivedBy:id,first_name,last_name'])->get();
        $live = $charges->reject(fn (Charge $charge) => $charge->isCancelled());
        $owed = (int) $live->sum(fn (Charge $charge) => $charge->balance());
        // A subscription added ahead of its due date is owed, but not yet due.
        $later = fn (Charge $charge) => $charge->due_on?->isFuture() === true;
        $dueNow = (int) $live->reject($later)->sum(fn (Charge $charge) => $charge->balance());
        $next = $live->filter(fn (Charge $charge) => $later($charge) && $charge->balance() > 0)->sortBy('due_on')->first();

        return [
            'totals' => [
                'owed' => $owed,
                'paid' => $live->sum(fn (Charge $charge) => $charge->paidAmount()),
                'waived' => $live->sum('waived_amount'),
                'due_now' => $dueNow,
                'due_now_usd' => $dueNow > 0 ? app(ExchangeRate::class)->dollars($dueNow) : null,
                'coming_up' => $next ? ['amount' => $next->balance(), 'on' => $next->due_on->format('j M Y'), 'label' => $next->label()] : null,
            ],
            'charges' => $charges->reverse()->values()->map(fn (Charge $charge) => [
                'id' => $charge->id,
                'label' => $charge->label(),
                'kind' => $charge->kind->value,
                'amount' => $charge->amount,
                'balance' => $charge->balance(),
                'paid' => $charge->paidAmount(),
                'status' => $charge->status(),
                'added_on' => $charge->created_at->format('j M Y'),
                'added_on_iso' => $charge->created_at->toDateString(),
                'due_on' => $charge->due_on?->format('j M Y'),
                'due_now' => ! $later($charge),
                'waiver' => $charge->waiver_type ? [
                    'type' => $charge->waiver_type->value,
                    'value' => $charge->waiver_value,
                    'description' => $charge->waiverDescription(),
                    'amount' => $charge->waived_amount,
                    'reason' => $charge->waiver_reason,
                    'by' => $forStaff ? $charge->waivedBy?->name : null,
                    'on' => $charge->waived_at?->format('j M Y'),
                    'on_iso' => $charge->waived_at?->toDateString(),
                ] : null,
                'cancelled' => $charge->isCancelled() ? [
                    'reason' => $charge->cancel_reason,
                    'on' => $charge->cancelled_at?->format('j M Y'),
                    'on_iso' => $charge->cancelled_at?->toDateString(),
                ] : null,
                'payments' => $charge->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'method' => $payment->method->label(),
                    'reference' => $payment->reference,
                    'paid_on' => $payment->paid_on->format('j M Y'),
                    'paid_on_iso' => $payment->paid_on->toDateString(),
                    'note' => $forStaff ? $payment->note : null,
                    'recorded_by' => $forStaff ? $payment->recordedBy?->name : null,
                    'voided' => $payment->isVoided() ? [
                        'reason' => $payment->void_reason,
                        'on' => $payment->voided_at?->format('j M Y'),
                    ] : null,
                    'receipt_url' => $payment->isVoided() ? null : route('receipts.show', $payment),
                ])->values(),
            ]),
            'history' => $forStaff
                ? $user->accountEvents()->with(['actor:id,first_name,last_name', 'charge', 'payment:id,receipt_number'])->limit(50)->get()
                    ->map(fn (AccountEvent $event) => [
                        'id' => $event->id,
                        'label' => $event->label(),
                        'charge' => $event->charge?->label(),
                        'receipt_number' => $event->payment?->receipt_number,
                        'amount' => $event->amount,
                        'note' => $event->note,
                        'who' => $event->actor?->name,
                        'when' => $event->created_at->format('j M Y, g:i a'),
                    ])
                : [],
        ];
    }
}
