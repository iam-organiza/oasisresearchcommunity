<?php

namespace App\Http\Controllers\Admin;

use App\Account\Account;
use App\Account\AccountStatement;
use App\Account\Fees;
use App\Enums\Account\ChargeKind;
use App\Enums\Account\PaymentMethod;
use App\Enums\Account\WaiverType;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Registrar's view of one person's fees and payments: add a charge,
 * waive one, record a bank transfer or cash, void a mistake.
 */
class AccountController extends Controller
{
    public function show(User $user): Response
    {
        $membership = $user->membership()->with('state.region')->first();
        $application = $user->latestApplication()->first();
        // Their grade, or the one they applied for (or were recommended) before joining.
        $grade = $membership !== null ? $membership->grade : ($application === null ? null : ($application->decided_grade ?? $application->grade));

        return Inertia::render('admin/accounts/show', [
            'person' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'number' => $membership?->number,
                'grade' => $grade?->label(),
                'status' => $membership?->statusLabel(),
                'active' => $membership?->isActive() ?? false,
                'application_id' => $application?->id,
            ],
            'statement' => AccountStatement::for($user, forStaff: true),
            'kinds' => collect(ChargeKind::cases())->map(fn (ChargeKind $kind) => ['value' => $kind->value, 'label' => $kind->label()]),
            'defaults' => [
                'exam' => app(Fees::class)->exam(),
                'induction' => $grade?->inductionFee(),
                'subscription' => $grade?->annualDues(),
            ],
            'waiverTypes' => collect(WaiverType::cases())->map(fn (WaiverType $type) => ['value' => $type->value, 'label' => $type->label()]),
            'methods' => collect(PaymentMethod::recordable())->map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()]),
            'year' => (int) now()->year,
        ]);
    }

    public function storeCharge(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ChargeKind::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'year' => ['nullable', 'required_if:kind,subscription', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
        ], [
            'kind.required' => 'Choose what the charge is for.',
            'amount.required' => 'Enter the amount in naira.',
            'amount.min' => 'Enter an amount of at least ₦1.',
            'year.required_if' => 'Enter the year this subscription covers.',
        ]);

        $kind = ChargeKind::from($data['kind']);

        if ($kind === ChargeKind::Induction && ! $user->membership()->exists()) {
            return $this->fail('amount', 'An induction fee is added when someone joins the register.');
        }

        $duplicate = $user->charges()->whereNull('cancelled_at')->where('kind', $kind->value)
            ->when($kind === ChargeKind::Subscription, fn ($query) => $query->where('year', $data['year']))
            ->exists();
        if ($duplicate && $kind !== ChargeKind::Exam) {
            return $this->fail('kind', $kind === ChargeKind::Subscription
                ? "There is already a subscription charge for {$data['year']}."
                : 'There is already an induction fee on this account.');
        }

        $charge = Account::addCharge($user, $kind, (int) $data['amount'], $request->user(), $data['year'] ?? null);

        return $this->done("{$charge->label()} of ₦".number_format($charge->amount).' added.');
    }

    public function waive(Request $request, Charge $charge): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(WaiverType::class)],
            'value' => ['nullable', 'required_unless:type,full', 'integer', 'min:1', Rule::when($request->input('type') === 'percent', 'max:100')],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'value.required_unless' => $request->input('type') === 'percent' ? 'Enter the percentage to waive.' : 'Enter the amount to waive.',
            'value.max' => 'A percentage can be at most 100.',
            'reason.required' => 'Say why, for example the scholarship it is for.',
        ]);

        Account::waive($charge, WaiverType::from($data['type']), isset($data['value']) ? (int) $data['value'] : null, $data['reason'], $request->user());

        return $this->done("{$charge->label()}: waiver saved.");
    }

    public function removeWaiver(Request $request, Charge $charge): RedirectResponse
    {
        Account::removeWaiver($charge, $request->user());

        return $this->done("{$charge->label()}: waiver removed.");
    }

    public function cancel(Request $request, Charge $charge): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why the charge is cancelled.']);

        Account::cancel($charge, $data['reason'], $request->user());

        return $this->done("{$charge->label()} cancelled.");
    }

    public function storePayment(Request $request, Charge $charge): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::recordable()))],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Enter the amount received, in naira.',
            'method.required' => 'Choose how it was paid.',
            'paid_on.required' => 'Enter the date it was paid.',
            'paid_on.before_or_equal' => 'The date cannot be in the future.',
        ]);

        $payment = Account::recordPayment(
            $charge,
            (int) $data['amount'],
            PaymentMethod::from($data['method']),
            CarbonImmutable::parse($data['paid_on']),
            $request->user(),
            $data['reference'] ?? null,
            $data['note'] ?? null,
        );

        return $this->done("Payment recorded as {$payment->receipt_number}. The receipt is on its way to {$charge->user->email}.");
    }

    public function voidPayment(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why the payment is voided.']);

        Account::voidPayment($payment, $data['reason'], $request->user());

        return $this->done("{$payment->receipt_number} voided.");
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function fail(string $field, string $message): RedirectResponse
    {
        return back()->withErrors([$field => $message]);
    }
}
