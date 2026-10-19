<?php

declare(strict_types=1);

namespace Domain\Booking;

/**
 * Lifecycle: Pending -> Confirmed (after payment) -> Cancelled.
 * A pending booking can also be cancelled, e.g. when payment fails.
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Confirmed || $next === self::Cancelled,
            self::Confirmed => $next === self::Cancelled,
            self::Cancelled => false,
        };
    }

    /**
     * Whether a booking in this status holds its time slot.
     * Mirrors the WHERE clause of the bookings_no_overlap exclusion constraint.
     */
    public function holdsSlot(): bool
    {
        return $this !== self::Cancelled;
    }
}
