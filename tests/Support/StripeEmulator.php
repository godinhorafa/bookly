<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

/**
 * A tiny, stateful stand-in for the parts of the Stripe API the adapter uses,
 * built from Stripe's documented responses (including test payment methods and
 * idempotency). It lets the adapter run the gateway contract offline; the same
 * contract runs against real Stripe test mode when STRIPE_TEST_SECRET is set.
 */
final class StripeEmulator
{
    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    private array $responsesByKey = [];

    /** @var array<string, array{amount: int, refunded: int}> */
    private array $intents = [];

    /** @var list<Request> */
    public array $requests = [];

    private int $sequence = 0;

    public function install(Factory $http): Factory
    {
        $http->fake(fn (Request $request): PromiseInterface => $this->handle($request));

        return $http;
    }

    private function handle(Request $request): PromiseInterface
    {
        $this->requests[] = $request;
        $key = $request->header('Idempotency-Key')[0] ?? null;

        if ($key !== null && isset($this->responsesByKey[$key])) {
            [$status, $body] = $this->responsesByKey[$key];

            return Factory::response($body, $status);
        }

        [$status, $body] = match (true) {
            str_ends_with($request->url(), '/payment_intents') => $this->createIntent($request->data()),
            str_ends_with($request->url(), '/refunds') => $this->createRefund($request->data()),
            default => [404, ['error' => ['type' => 'invalid_request_error', 'message' => 'Unrecognized request URL']]],
        };

        if ($key !== null && $status < 500) {
            $this->responsesByKey[$key] = [$status, $body];
        }

        return Factory::response($body, $status);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function createIntent(array $data): array
    {
        $id = sprintf('pi_emulated_%06d', ++$this->sequence);

        $decline = match ($data['payment_method']) {
            'pm_card_visa', 'pm_card_mastercard' => null,
            'pm_card_chargeDeclined' => 'generic_decline',
            'pm_card_visa_chargeDeclinedInsufficientFunds' => 'insufficient_funds',
            'pm_emulator_server_error' => 'server_error',
            default => 'invalid',
        };

        if ($decline === null) {
            $this->intents[$id] = ['amount' => (int) $data['amount'], 'refunded' => 0];

            return [200, ['id' => $id, 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => (int) $data['amount'], 'currency' => $data['currency']]];
        }

        return match ($decline) {
            'server_error' => [500, ['error' => ['type' => 'api_error', 'message' => "Something went wrong on Stripe's end."]]],
            'invalid' => [400, ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such PaymentMethod']]],
            default => [402, ['error' => [
                'type' => 'card_error',
                'code' => 'card_declined',
                'decline_code' => $decline,
                'message' => 'Your card was declined.',
                'payment_intent' => ['id' => $id, 'status' => 'requires_payment_method'],
            ]]],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function createRefund(array $data): array
    {
        $intent = (string) $data['payment_intent'];
        $amount = (int) $data['amount'];

        if (! isset($this->intents[$intent])) {
            return [404, ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => "No such payment_intent: '{$intent}'"]]];
        }

        $left = $this->intents[$intent]['amount'] - $this->intents[$intent]['refunded'];
        if ($amount > $left) {
            return [400, ['error' => [
                'type' => 'invalid_request_error',
                'code' => 'charge_exceeds_source_limit',
                'message' => "Refund amount ({$amount}) is greater than unrefunded amount on charge ({$left})",
            ]]];
        }

        $this->intents[$intent]['refunded'] += $amount;

        return [200, ['id' => sprintf('re_emulated_%06d', ++$this->sequence), 'object' => 'refund', 'status' => 'succeeded', 'amount' => $amount]];
    }
}
