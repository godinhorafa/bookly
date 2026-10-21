<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent;

use Domain\Booking\BookingId;
use Domain\Payment\Payment;
use Domain\Payment\PaymentId;
use Domain\Payment\PaymentRepository;
use Domain\Payment\PaymentStatus;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Infrastructure\Persistence\Eloquent\Models\PaymentModel;

final class EloquentPaymentRepository implements PaymentRepository
{
    public function save(Payment $payment): void
    {
        PaymentModel::query()->updateOrCreate(['id' => $payment->id->value], [
            'booking_id' => $payment->bookingId->value,
            'attempt' => $payment->attempt,
            'amount' => $payment->amount->amount,
            'refunded_amount' => $payment->refunded()->amount,
            'currency' => $payment->amount->currency->value,
            'gateway' => $payment->gateway,
            'reference' => $payment->reference,
            'refund_reference' => $payment->refundReference(),
            'status' => $payment->status()->value,
            'decline_reason' => $payment->declineReason,
        ]);
    }

    public function forBooking(BookingId $bookingId): array
    {
        $models = PaymentModel::query()
            ->where('booking_id', $bookingId->value)
            ->orderBy('attempt')
            ->get();

        return array_values($models->map($this->toDomain(...))->all());
    }

    private function toDomain(PaymentModel $model): Payment
    {
        $currency = Currency::from($model->currency);

        return Payment::reconstitute(
            PaymentId::fromString($model->id),
            BookingId::fromString($model->booking_id),
            $model->attempt,
            Money::of($model->amount, $currency),
            $model->gateway,
            $model->reference,
            PaymentStatus::from($model->status),
            Money::of($model->refunded_amount, $currency),
            $model->decline_reason,
            $model->refund_reference,
        );
    }
}
