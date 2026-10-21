<?php

declare(strict_types=1);

use Infrastructure\Notification\WebhookSignature;

it('produces a signature that verifies with the same secret', function (): void {
    $header = WebhookSignature::sign('{"id":"1"}', 'whsec_test', 1_790_000_000);

    expect($header)->toMatch('/^t=1790000000,v1=[0-9a-f]{64}$/')
        ->and(WebhookSignature::verify('{"id":"1"}', $header, 'whsec_test', 1_790_000_060))->toBeTrue();
});

it('rejects tampered bodies, wrong secrets, stale timestamps and garbage', function (string $body, string $secret, int $now, ?string $header = null): void {
    $header ??= WebhookSignature::sign('{"id":"1"}', 'whsec_test', 1_790_000_000);

    expect(WebhookSignature::verify($body, $header, $secret, $now))->toBeFalse();
})->with([
    'tampered body' => ['{"id":"2"}', 'whsec_test', 1_790_000_000],
    'wrong secret' => ['{"id":"1"}', 'whsec_other', 1_790_000_000],
    'replayed after 10 min' => ['{"id":"1"}', 'whsec_test', 1_790_000_600],
    'malformed header' => ['{"id":"1"}', 'whsec_test', 1_790_000_000, 'v1=abc'],
]);
