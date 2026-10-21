<?php

declare(strict_types=1);

namespace Domain\Payment;

use Domain\Booking\BookingId;

interface PaymentRepository
{
    public function save(Payment $payment): void;

    /**
     * Every payment attempt for the booking, in attempt order.
     *
     * @return list<Payment>
     */
    public function forBooking(BookingId $bookingId): array;
}
