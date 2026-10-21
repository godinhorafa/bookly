<?php

declare(strict_types=1);

namespace Infrastructure\Payment;

use Domain\Payment\ChargeResult;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\Exception\RefundRejected;
use Domain\Payment\PaymentGateway;
use Domain\Shared\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Adapter for Stripe's REST API (PaymentIntents + Refunds), with no SDK: the
 * surface we need is small and this keeps the translation explicit.
 *
 * Stripe errors are mapped to domain terms:
 *  - 402 card_error        → ChargeResult::declined(decline_code)
 *  - 5xx / network failure → PaymentGatewayUnavailable (retry with the same key)
 *  - 4xx on refunds        → RefundRejected
 *
 * @see https://docs.stripe.com/api/payment_intents/create
 * @see https://docs.stripe.com/api/idempotent_requests
 */
final readonly class StripePaymentGateway implements PaymentGateway
{
    public function __construct(
        private Http $http,
        private string $secretKey,
        private string $baseUrl = 'https://api.stripe.com/v1',
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function charge(string $idempotencyKey, Money $amount, string $paymentMethod, string $description): ChargeResult
    {
        $response = $this->send($idempotencyKey, 'payment_intents', [
            'amount' => $amount->amount,
            'currency' => strtolower($amount->currency->value),
            'payment_method' => $paymentMethod,
            'confirm' => 'true',
            'description' => $description,
            // Server-side confirmation only: no redirect-based payment methods.
            'automatic_payment_methods[enabled]' => 'true',
            'automatic_payment_methods[allow_redirects]' => 'never',
        ]);

        if ($response->successful()) {
            $status = $response->json('status');
            $reference = $this->string($response->json('id'));

            return $status === 'succeeded'
                ? ChargeResult::succeeded($reference)
                : ChargeResult::declined("unexpected_status_{$this->string($status)}", $reference);
        }

        if ($response->json('error.type') === 'card_error') {
            return ChargeResult::declined(
                $this->string($response->json('error.decline_code') ?? $response->json('error.code') ?? 'card_declined'),
                is_string($response->json('error.payment_intent.id')) ? $response->json('error.payment_intent.id') : null,
            );
        }

        throw $this->unavailable($response);
    }

    public function refund(string $idempotencyKey, string $chargeReference, Money $amount): string
    {
        $response = $this->send($idempotencyKey, 'refunds', [
            'payment_intent' => $chargeReference,
            'amount' => $amount->amount,
        ]);

        if ($response->successful()) {
            return $this->string($response->json('id'));
        }

        if ($response->clientError()) {
            throw RefundRejected::byGateway($this->name(), $this->string($response->json('error.message') ?? 'request rejected'));
        }

        throw $this->unavailable($response);
    }

    /**
     * @param  array<string, string|int>  $form
     */
    private function send(string $idempotencyKey, string $path, array $form): Response
    {
        try {
            return $this->client()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($path, $form);
        } catch (ConnectionException $e) {
            throw PaymentGatewayUnavailable::because($this->name(), $e->getMessage(), $e);
        }
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->asForm()
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function unavailable(Response $response): PaymentGatewayUnavailable
    {
        return PaymentGatewayUnavailable::because(
            $this->name(),
            "HTTP {$response->status()}: " . $this->string($response->json('error.message') ?? 'no error message'),
        );
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
