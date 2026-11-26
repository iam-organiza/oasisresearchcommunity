<?php

namespace App\Account;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Paystack: starts a transaction and checks one. Also works out
 * Paystack's charge, which is added to what the member pays.
 */
final class Paystack
{
    public function enabled(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    public function usdEnabled(): bool
    {
        return $this->enabled() && (bool) config('services.paystack.usd_enabled');
    }

    /**
     * What to charge, in kobo or cents, so that `$minor` reaches OASIS after
     * Paystack takes its fee. Naira: 1.5% + ₦100 (no ₦100 under ₦2,500),
     * capped at ₦2,000. Dollars: 3.9%. Rounded up to a whole naira or cent.
     */
    public static function grossUp(int $minor, string $currency): int
    {
        if ($currency === 'USD') {
            $percent = (float) config('services.paystack.fees.usd.percent');

            return (int) ceil($minor / (1 - $percent / 100));
        }

        $fees = config('services.paystack.fees.ngn');
        $rate = 1 - ((float) $fees['percent']) / 100;
        $flat = ((int) $fees['flat']) * 100;
        $cap = ((int) $fees['cap']) * 100;

        $total = (int) ceil(($minor + $flat) / $rate / 100) * 100;
        if ($total < ((int) $fees['flat_waived_below']) * 100) {
            $total = (int) ceil($minor / $rate / 100) * 100;
        }

        return min($total, $minor + $cap);
    }

    /**
     * Starts a transaction. Returns the page to send the member to.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initialize(string $email, int $totalMinor, string $currency, string $reference, string $callbackUrl, array $metadata): string
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $totalMinor,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        $url = $response->json('data.authorization_url');
        if (! $response->successful() || $response->json('status') !== true || ! is_string($url)) {
            throw new RuntimeException('Paystack did not start the payment: '.($response->json('message') ?? $response->status()));
        }

        return $url;
    }

    /**
     * What Paystack knows about a transaction, or null if it could not be
     * reached (try again later).
     *
     * @return array{status: string, reference: string, amount: int, currency: string, id: string, channel: ?string, paid_at: ?string, gateway_response: ?string}|null
     */
    public function verify(string $reference): ?array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        if ($response->status() === 404 || $response->json('message') === 'Transaction reference not found') {
            return ['status' => 'not_found', 'reference' => $reference, 'amount' => 0, 'currency' => '', 'id' => '', 'channel' => null, 'paid_at' => null, 'gateway_response' => null];
        }
        if (! $response->successful() || $response->json('status') !== true) {
            return null;
        }

        return [
            'status' => (string) $response->json('data.status'),
            'reference' => (string) $response->json('data.reference'),
            'amount' => (int) $response->json('data.amount'),
            'currency' => strtoupper((string) $response->json('data.currency')),
            'id' => (string) $response->json('data.id'),
            'channel' => $response->json('data.channel'),
            'paid_at' => $response->json('data.paid_at'),
            'gateway_response' => $response->json('data.gateway_response'),
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(8);
    }
}
