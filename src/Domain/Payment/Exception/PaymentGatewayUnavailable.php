<?php

declare(strict_types=1);

namespace Domain\Payment\Exception;

use Domain\Shared\Exception\DomainException;

/**
 * The provider could not be reached or failed on its side. Nothing is known to
 * have been charged; retrying with the same idempotency key is safe.
 */
final class PaymentGatewayUnavailable extends DomainException
{
    public static function because(string $gateway, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Payment gateway {$gateway} is unavailable: {$reason}", 0, $previous);
    }
}
