<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

/**
 * Signs webhook bodies the way Stripe does: "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<body>">".
 * The timestamp lets receivers reject replays; the HMAC proves the sender knows the secret.
 */
final class WebhookSignature
{
    public static function sign(string $body, string $secret, int $timestamp): string
    {
        return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$body}", $secret));
    }

    public static function verify(string $body, string $header, string $secret, int $now, int $toleranceSeconds = 300): bool
    {
        if (preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $header, $m) !== 1) {
            return false;
        }

        $timestamp = (int) $m[1];
        if (abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$timestamp}.{$body}", $secret), $m[2]);
    }
}
