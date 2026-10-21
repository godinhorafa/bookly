<?php

declare(strict_types=1);

namespace Domain\Payment\Exception;

use Domain\Shared\Exception\DomainException;

final class RefundRejected extends DomainException
{
    public static function byGateway(string $gateway, string $reason): self
    {
        return new self("Payment gateway {$gateway} rejected the refund: {$reason}");
    }
}
