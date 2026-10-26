<?php

declare(strict_types=1);

namespace Application\Payment;

use Application\Event\Outbox;
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
use Domain\Shared\Clock;

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
        private Outbox $outbox,
        private Transaction $transaction,
        private Clock $clock,
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

        // Checked before charging. Expiry waits a grace period longer than any
        // charge takes, so a payment that starts in time is never overtaken (ADR 0010).
        if ($booking->holdHasEnded($this->clock->now())) {
            throw BookingNotPayable::holdEnded($id, $booking->holdUntil);
        }

        // Fully discounted bookings have nothing to charge (providers reject zero amounts).
        if ($booking->price->isZero()) {
            $booking->confirm();
            $this->transaction->run(function () use ($booking): void {
                $this->bookings->save($booking);
                $this->outbox->append($this->clock->now(), ...$booking->releaseEvents());
            });

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
            $this->outbox->append($this->clock->now(), ...$booking->releaseEvents());
        });

        return $payment->succeeded()
            ? PaymentOutcome::confirmed($payment->id->value)
            : PaymentOutcome::declined($payment->id->value, $payment->declineReason ?? 'declined');
    }
}
