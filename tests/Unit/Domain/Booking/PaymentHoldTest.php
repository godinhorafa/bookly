<?php

declare(strict_types=1);

use Domain\Booking\PaymentHold;

it('counts the hold from when the booking was requested', function (): void {
    expect((new PaymentHold(15))->from(new DateTimeImmutable('2026-11-01T12:00:00Z'))->format(DATE_ATOM))
        ->toBe('2026-11-01T12:15:00+00:00');
});

it('lasts at least a minute', function (int $minutes): void {
    new PaymentHold($minutes);
})->with([0, -5])->throws(InvalidArgumentException::class, 'at least 1 minute');

it('accepts a one-minute hold', function (): void {
    expect((new PaymentHold(1))->minutes)->toBe(1);
});
