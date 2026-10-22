<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent;

use Domain\Booking\Booking;
use Domain\Booking\BookingCancellation;
use Domain\Booking\BookingId;
use Domain\Booking\BookingRepository;
use Domain\Booking\BookingStatus;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Space\SpaceId;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Infrastructure\Persistence\Postgres\Timestamptz;
use Infrastructure\Persistence\Postgres\TstzRange;

final readonly class EloquentBookingRepository implements BookingRepository
{
    private const string EXCLUSION_VIOLATION = '23P01';

    private const string NO_OVERLAP_CONSTRAINT = 'bookings_no_overlap';

    public function __construct(private ConnectionInterface $connection) {}

    public function get(BookingId $id): Booking
    {
        $model = BookingModel::query()->find($id->value);

        if (! $model instanceof BookingModel) {
            throw BookingNotFound::withId($id);
        }

        return $this->toDomain($model);
    }

    public function save(Booking $booking): void
    {
        try {
            // A nested call becomes a SAVEPOINT, so a violation never aborts the caller's transaction.
            $this->connection->transaction(fn () => BookingModel::query()->updateOrCreate(
                ['id' => $booking->id->value],
                [
                    'space_id' => $booking->spaceId->value,
                    'user_id' => $booking->customerId->value,
                    'status' => $booking->status()->value,
                    'total_amount' => $booking->price->amount,
                    'currency' => $booking->price->currency->value,
                    'period' => TstzRange::fromTimeSlot($booking->slot),
                    'cancelled_at' => Timestamptz::toDatabase($booking->cancellation()?->cancelledAt),
                    'refund_amount' => $booking->cancellation()?->refund->amount,
                ],
            ));
        } catch (QueryException $e) {
            if ($this->isOverlapViolation($e)) {
                throw SlotUnavailable::for($booking->spaceId, $booking->slot);
            }

            throw $e;
        }
    }

    public function findOverlapping(SpaceId $spaceId, TimeSlot $slot): array
    {
        $models = BookingModel::query()
            ->where('space_id', $spaceId->value)
            ->where('status', '<>', BookingStatus::Cancelled->value)
            ->whereRaw('period && ?::tstzrange', [TstzRange::fromTimeSlot($slot)])
            ->orderByRaw('lower(period)')
            ->get();

        return array_values($models->map($this->toDomain(...))->all());
    }

    public function countUpcomingFor(CustomerId $customerId, \DateTimeImmutable $now): int
    {
        return BookingModel::query()
            ->where('user_id', $customerId->value)
            ->where('status', '<>', BookingStatus::Cancelled->value)
            ->whereRaw('upper(period) > ?::timestamptz', [Timestamptz::toDatabase($now)])
            ->count();
    }

    public function forCustomer(CustomerId $customerId): array
    {
        $models = BookingModel::query()
            ->where('user_id', $customerId->value)
            ->orderByRaw('lower(period) DESC')
            ->get();

        return array_values($models->map($this->toDomain(...))->all());
    }

    private function isOverlapViolation(QueryException $e): bool
    {
        return $e->getCode() === self::EXCLUSION_VIOLATION
            && str_contains($e->getMessage(), self::NO_OVERLAP_CONSTRAINT);
    }

    private function toDomain(BookingModel $model): Booking
    {
        $currency = Currency::from($model->currency);
        $cancelledAt = Timestamptz::fromDatabase($model->cancelled_at);

        return Booking::reconstitute(
            BookingId::fromString($model->id),
            SpaceId::fromString($model->space_id),
            new CustomerId($model->user_id),
            TstzRange::toTimeSlot($model->period),
            Money::of($model->total_amount, $currency),
            BookingStatus::from($model->status),
            $cancelledAt instanceof \DateTimeImmutable && $model->refund_amount !== null
                ? new BookingCancellation($cancelledAt, Money::of($model->refund_amount, $currency))
                : null,
        );
    }
}
