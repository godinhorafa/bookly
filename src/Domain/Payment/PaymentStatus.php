<?php

declare(strict_types=1);

namespace Domain\Payment;

enum PaymentStatus: string
{
    case Succeeded = 'succeeded';
    case Declined = 'declined';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
