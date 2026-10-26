<?php

declare(strict_types=1);

namespace Domain\Booking;

/**
 * Lifecycle: Pending -> Confirmed (after payment) -> Cancelled.
 * A pending booking can also be cancelled, or expire when it is not paid in time.
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Confirmed, self::Cancelled, self::Expired], true),
            self::Confirmed => $next === self::Cancelled,
            self::Cancelled, self::Expired => false,
        };
    }

    /**
     * Whether a booking in this status holds its time slot.
     * Mirrors the WHERE clause of the bookings_no_overlap exclusion constraint.
     */
    public function holdsSlot(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }
}
