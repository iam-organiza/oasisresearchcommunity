<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Induction packs to post: new members who chose to have their pack sent
 * rather than attend the ceremony. The Secretariat marks each one posted,
 * then delivered, and the delivery address is deleted on delivery.
 */
class InductionPackController extends Controller
{
    private const TABS = ['to_post', 'posted', 'delivered'];

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'to_post';
        $scope = fn (string $which) => fn (Builder $query) => match ($which) {
            'to_post' => $query->whereNull('pack_posted_at'),
            'posted' => $query->whereNotNull('pack_posted_at')->whereNull('pack_delivered_at'),
            default => $query->whereNotNull('pack_delivered_at'),
        };
        $base = fn () => Membership::query()->where('induction_delivery', 'post');

        return Inertia::render('admin/packs/index', [
            'tab' => $tab,
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $which) => [$which => $base()->where($scope($which))->count()]),
            'members' => $base()->where($scope($tab))
                ->with(['user:id,first_name,last_name,email', 'deliveryState'])
                ->orderBy($tab === 'to_post' ? 'updated_at' : ($tab === 'posted' ? 'pack_posted_at' : 'pack_delivered_at'), $tab === 'to_post' ? 'asc' : 'desc')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Membership $membership) => [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'number' => $membership->number,
                    'grade' => $membership->grade->label(),
                    'active' => $membership->isActive(),
                    'address' => collect([$membership->delivery_address, $membership->delivery_city, $membership->deliveryState?->name])->filter()->implode(', '),
                    'phone' => $membership->delivery_phone,
                    'posted' => $membership->pack_posted_at?->format('j M Y'),
                    'delivered' => $membership->pack_delivered_at?->format('j M Y'),
                ]),
        ]);
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        abort_unless($membership->induction_delivery === 'post', 404);
        $data = $request->validate(['status' => ['required', Rule::in(['posted', 'delivered'])]]);

        if ($data['status'] === 'posted') {
            if (! $membership->isActive()) {
                return back()->withErrors(['status' => 'Post the pack once their induction fee is settled and the membership is active.']);
            }
            $membership->forceFill(['pack_posted_at' => now()])->save();
            $message = "Marked as posted to {$membership->user->name}.";
        } else {
            // Delivered: the address is no longer needed (privacy policy).
            $membership->forceFill([
                'pack_posted_at' => $membership->pack_posted_at ?? now(),
                'pack_delivered_at' => now(),
                'delivery_address' => null, 'delivery_city' => null, 'delivery_state_id' => null, 'delivery_phone' => null,
            ])->save();
            $message = "Marked as delivered. {$membership->user->name}'s address is deleted.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
