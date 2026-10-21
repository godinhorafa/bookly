<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use Domain\Booking\BookingId;
use Domain\Payment\Payment;
use Domain\Payment\PaymentRepository;

final class InMemoryPaymentRepository implements PaymentRepository
{
    /** @var array<string, Payment> */
    private array $payments = [];

    public function save(Payment $payment): void
    {
        $this->payments[$payment->id->value] = clone $payment;
    }

    public function forBooking(BookingId $bookingId): array
    {
        $payments = array_filter($this->payments, static fn (Payment $p): bool => $p->bookingId->equals($bookingId));
        usort($payments, static fn (Payment $a, Payment $b): int => $a->attempt <=> $b->attempt);

        return array_map(static fn (Payment $p): Payment => clone $p, $payments);
    }
}
