<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Booking\Booking;
use Domain\Booking\BookingId;
use Domain\Booking\CustomerId;
use Domain\Booking\TimeSlot;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Space\Space;
use Domain\Space\SpaceId;

/**
 * Object mothers with sensible defaults, so each test states only what matters to it.
 */
final class Builders
{
    public static function space(?SpaceId $id = null, int $hourlyRate = 5_000, string $name = 'Meeting room'): Space
    {
        return new Space($id ?? SpaceId::generate(), $name, 'America/Sao_Paulo', 8, Money::of($hourlyRate, Currency::BRL));
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
}
