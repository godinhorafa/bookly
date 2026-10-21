<?php

declare(strict_types=1);

namespace Domain\Payment\Exception;

use Domain\Payment\PaymentId;
use Domain\Shared\Exception\DomainException;
use Domain\Shared\Money;

final class InvalidRefund extends DomainException
{
    public static function exceedsRefundable(PaymentId $id, Money $requested, Money $refundable): self
    {
        return new self("Cannot refund {$requested->format()} on payment {$id}: only {$refundable->format()} is refundable.");
    }
}
