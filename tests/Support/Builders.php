<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\CustomerId;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Space\Space;
use Domain\Space\SpaceId;

/**
 * Object mothers with sensible defaults, so each test states only what matters to it.
 */
final class Builders
{
    public static function space(
        ?SpaceId $id = null,
        int $hourlyRate = 5_000,
        string $name = 'Meeting room',
        string $timezone = 'America/Sao_Paulo',
        CancellationPolicyType $policy = CancellationPolicyType::Moderate,
    ): Space {
        return new Space($id ?? SpaceId::generate(), $name, $timezone, 8, Money::of($hourlyRate, Currency::BRL), $policy);
    }

    public static function booking(
        SpaceId $spaceId,
        CustomerId $customerId,
        string $start = '2026-11-02T10:00:00Z',
        string $end = '2026-11-02T11:00:00Z',
    ): Booking {
        return Booking::request(
            BookingId::generate(),
            $spaceId,
            $customerId,
            TimeSlot::fromStrings($start, $end),
            Money::of(5_000, Currency::BRL),
        );
    }

    /**
     * Cancels well before the default slot under the moderate policy.
     */
    public static function cancel(Booking $booking, string $at = '2026-10-01T00:00:00Z'): Booking
    {
        $booking->cancel(new \DateTimeImmutable($at), CancellationPolicyType::Moderate->policy());

        return $booking;
    }
}
