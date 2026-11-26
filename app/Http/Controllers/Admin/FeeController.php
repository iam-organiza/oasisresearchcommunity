<?php

namespace App\Http\Controllers\Admin;

use App\Account\ExchangeRate;
use App\Account\Fees;
use App\Enums\Membership\Grade;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Fees screen: every grade's induction and yearly fee, the exam fee,
 * the upgrade charge and the dollar rate, with the history of changes. A
 * change applies to charges added from then on; charges already on an
 * account keep their amount.
 */
class FeeController extends Controller
{
    public function index(Fees $fees, ExchangeRate $rates): Response
    {
        $latest = $rates->latest();

        return Inertia::render('admin/fees/index', [
            'grades' => collect(Grade::cases())->map(fn (Grade $grade) => [
                'value' => $grade->value,
                'label' => $grade->label(),
                'induction' => $fees->induction($grade),
                'subscription' => $fees->subscription($grade),
            ]),
            'exam' => $fees->exam(),
            'upgradePercent' => $fees->upgradePercent(),
            'rate' => [
                'current' => $rates->current(),
                'override' => $fees->rateOverride(),
                'fetched' => $latest ? ['rate' => $latest['rate'], 'on' => $latest['fetched_at']->format('j M Y, g:i a')] : null,
                'stale' => $rates->isStale(),
                'margin' => (int) config('membership.dollar_margin_percent'),
                'attribution' => ExchangeRate::ATTRIBUTION_URL,
            ],
            'history' => DB::table('price_setting_changes')
                ->leftJoin('users', 'users.id', '=', 'price_setting_changes.user_id')
                ->orderByDesc('price_setting_changes.id')
                ->limit(50)
                ->get(['price_setting_changes.*', 'users.first_name', 'users.last_name'])
                ->map(fn (object $change) => [
                    'id' => $change->id,
                    'key' => $change->key,
                    'label' => Fees::label($change->key),
                    'old' => $change->old_amount === null ? null : (int) $change->old_amount,
                    'new' => $change->new_amount === null ? null : (int) $change->new_amount,
                    'reason' => $change->reason,
                    'who' => $change->first_name ? trim("{$change->first_name} {$change->last_name}") : null,
                    'when' => CarbonImmutable::parse($change->created_at)->format('j M Y, g:i a'),
                ]),
        ]);
    }

    public function update(Request $request, Fees $fees): RedirectResponse
    {
        $grades = array_map(fn (Grade $grade) => $grade->value, Grade::cases());
        $rules = [
            'exam' => ['required', 'integer', 'min:0', 'max:100000000'],
            'upgrade_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'usd_rate_override' => ['nullable', 'integer', 'min:100', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
        $messages = [
            'reason.required' => 'Say why, for example the Council decision it follows.',
            'upgrade_percent.max' => 'The upgrade charge can be at most 100%.',
            'usd_rate_override.min' => 'Enter naira per dollar, for example 1550.',
            'usd_rate_override.max' => 'Enter naira per dollar, for example 1550.',
        ];
        foreach ($grades as $grade) {
            foreach (['induction', 'subscription'] as $kind) {
                $rules["{$kind}.{$grade}"] = ['required', 'integer', 'min:0', 'max:100000000'];
                $messages["{$kind}.{$grade}.required"] = 'Enter an amount in naira.';
            }
        }
        $data = $request->validate($rules, $messages);

        $amounts = [
            'exam' => (int) $data['exam'],
            'upgrade_percent' => (int) $data['upgrade_percent'],
            'usd_rate_override' => isset($data['usd_rate_override']) ? (int) $data['usd_rate_override'] : null,
        ];
        foreach ($grades as $grade) {
            $amounts["induction.{$grade}"] = (int) $data['induction'][$grade];
            $amounts["subscription.{$grade}"] = (int) $data['subscription'][$grade];
        }

        $changed = $fees->update($amounts, $data['reason'], $request->user());

        Inertia::flash('toast', $changed === []
            ? ['type' => 'success', 'message' => 'Nothing changed.']
            : ['type' => 'success', 'message' => count($changed) === 1 ? 'Saved 1 change.' : 'Saved '.count($changed).' changes.']);

        return back();
    }
}
