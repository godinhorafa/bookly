<?php

declare(strict_types=1);

namespace Application\Payment;

use Application\Transaction;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Payment\Exception\BookingNotPayable;
use Domain\Payment\Payment;
use Domain\Payment\PaymentGateways;
use Domain\Payment\PaymentId;
use Domain\Payment\PaymentRepository;

/**
 * Charges a pending booking and confirms it.
 *
 * The gateway is called *outside* the database transaction: holding locks while
 * waiting on a network call is a bad idea, and a rollback can't undo a charge
 * anyway. Instead the idempotency key is deterministic per attempt, so if
 * saving fails after a successful charge, retrying replays the same charge
 * rather than taking the money twice.
 */
final readonly class PayForBookingHandler
{
    public function __construct(
        private BookingRepository $bookings,
        private PaymentRepository $payments,
        private PaymentGateways $gateways,
        private Transaction $transaction,
    ) {}

    public function handle(PayForBooking $command): PaymentOutcome
    {
        $id = BookingId::fromString($command->bookingId);
        $booking = $this->bookings->get($id);

        if (! $booking->customerId->equals(new CustomerId($command->customerId))) {
            throw BookingNotFound::withId($id);
        }

        if ($booking->status() !== BookingStatus::Pending) {
            throw BookingNotPayable::because($id, $booking->status());
        }

        // Fully discounted bookings have nothing to charge (providers reject zero amounts).
        if ($booking->price->isZero()) {
            $booking->confirm();
            $this->bookings->save($booking);

            return PaymentOutcome::confirmed(null);
        }

        $attempt = count($this->payments->forBooking($id)) + 1;
        $gateway = $this->gateways->default();

        $result = $gateway->charge(
            "booking-{$id}-charge-{$attempt}",
            $booking->price,
            $command->paymentMethod,
            "Booking {$id}",
        );

        $payment = Payment::fromCharge(PaymentId::generate(), $id, $attempt, $booking->price, $gateway->name(), $result);

        if ($payment->succeeded()) {
            $booking->confirm();
        }

        $this->transaction->run(function () use ($payment, $booking): void {
            $this->payments->save($payment);
            $this->bookings->save($booking);
        });

        return $payment->succeeded()
            ? PaymentOutcome::confirmed($payment->id->value)
            : PaymentOutcome::declined($payment->id->value, $payment->declineReason ?? 'declined');
    }
}
