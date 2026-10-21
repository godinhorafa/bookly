<?php

declare(strict_types=1);

namespace Application\Booking;

use Application\Transaction;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Payment\Payment;
use Domain\Payment\PaymentGateways;
use Domain\Payment\PaymentRepository;
use Domain\Shared\Clock;
use Domain\Shared\Money;
use Domain\Space\SpaceRepository;

final readonly class CancelBookingHandler
{
    public function __construct(
        private BookingRepository $bookings,
        private SpaceRepository $spaces,
        private PaymentRepository $payments,
        private PaymentGateways $gateways,
        private Transaction $transaction,
        private Clock $clock,
    ) {}

    /**
     * @return Money the refund sent back to the customer
     */
    public function handle(CancelBooking $command): Money
    {
        $id = BookingId::fromString($command->bookingId);
        $booking = $this->bookings->get($id);

        // Someone else's booking looks exactly like a missing one, so ids can't be probed.
        if (! $booking->customerId->equals(new CustomerId($command->customerId))) {
            throw BookingNotFound::withId($id);
        }

        $space = $this->spaces->get($booking->spaceId);
        $refund = $booking->cancel($this->clock->now(), $space->cancellationPolicy->policy());
        $payment = $refund->isZero() ? null : $this->refund($id, $refund);

        // The refund call happens first: if the gateway fails, nothing is saved and the booking stays active.
        $this->transaction->run(function () use ($booking, $payment): void {
            $this->bookings->save($booking);

            if ($payment instanceof Payment) {
                $this->payments->save($payment);
            }
        });

        return $refund;
    }

    private function refund(BookingId $id, Money $amount): Payment
    {
        $payment = $this->settledPaymentOf($id);
        $gateway = $this->gateways->named($payment->gateway);

        $reference = $gateway->refund("booking-{$id}-refund", $payment->chargeReference(), $amount);
        $payment->recordRefund($amount, $reference);

        return $payment;
    }

    private function settledPaymentOf(BookingId $id): Payment
    {
        foreach (array_reverse($this->payments->forBooking($id)) as $payment) {
            if ($payment->succeeded()) {
                return $payment;
            }
        }

        // A confirmed booking with a refund due always has a successful payment.
        throw new \LogicException("Booking {$id} is owed a refund but has no successful payment.");
    }
}
