<?php

declare(strict_types=1);

namespace Application\Payment;

final readonly class PayForBooking
{
    public function __construct(
        public string $bookingId,
        public int $customerId,
        public string $paymentMethod,
    ) {}
}
