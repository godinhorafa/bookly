<?php

declare(strict_types=1);

use Domain\Booking\CustomerId;
use Domain\Booking\Exception\BookingRulesViolated;
use Domain\Booking\Specification\AlignedToGrid;
use Domain\Booking\Specification\AllOf;
use Domain\Booking\Specification\BelowActiveBookingLimit;
use Domain\Booking\Specification\BookingRequest;
use Domain\Booking\Specification\BookingRules;
use Domain\Booking\Specification\DurationBetween;
use Domain\Booking\Specification\StartsWithMinimumNotice;
use Domain\Booking\Specification\WithinBookingHorizon;
use Domain\Booking\Specification\WithinOpeningHours;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Shared\TimeSlot;
use Domain\Shared\WeeklyWindow;
use Domain\Space\OpeningHours;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Tests\Support\Builders;

const NOW = '2026-11-02T12:00:00Z'; // Monday, 09:00 in São Paulo

function bookingRequest(string $start, string $end, ?Space $space = null, int $active = 0): BookingRequest
{
    return new BookingRequest(
        $space ?? Builders::space(),
        TimeSlot::fromStrings($start, $end),
        new CustomerId(1),
        new DateTimeImmutable(NOW),
        $active,
    );
}

describe('minimum notice', function (): void {
    it('accepts bookings far enough ahead', function (string $start, bool $ok): void {
        $spec = new StartsWithMinimumNotice(60);

        expect($spec->isSatisfiedBy(bookingRequest($start, '2026-11-02T18:00:00Z')))->toBe($ok);
    })->with([
        'exactly 60 min' => ['2026-11-02T13:00:00Z', true],
        '59 min' => ['2026-11-02T12:59:00Z', false],
        'in the past' => ['2026-11-02T11:00:00Z', false],
    ]);

    it('reads naturally when no notice is required', function (): void {
        expect((new StartsWithMinimumNotice(0))->describe())->toBe('Bookings must start in the future.');
    });
});

it('limits how far ahead bookings can be made', function (string $start, bool $ok): void {
    expect((new WithinBookingHorizon(30))->isSatisfiedBy(bookingRequest($start, '2026-12-03T00:00:00Z')))->toBe($ok);
})->with([
    'exactly 30 days' => ['2026-12-02T12:00:00Z', true],
    '30 days and a minute' => ['2026-12-02T12:01:00Z', false],
]);

it('explains the booking horizon', function (): void {
    expect((new WithinBookingHorizon(90))->violationsOf(bookingRequest('2027-06-01T10:00:00Z', '2027-06-01T11:00:00Z')))
        ->toBe(['Bookings can be made at most 90 days in advance.']);
});

it('bounds the duration', function (string $end, bool $ok): void {
    expect((new DurationBetween(30, 240))->isSatisfiedBy(bookingRequest('2026-11-03T10:00:00Z', $end)))->toBe($ok);
})->with([
    '29 min' => ['2026-11-03T10:29:00Z', false],
    '30 min' => ['2026-11-03T10:30:00Z', true],
    '4h' => ['2026-11-03T14:00:00Z', true],
    '4h01' => ['2026-11-03T14:01:00Z', false],
]);

it('checks the grid in the space local time', function (): void {
    $kolkata = new Space(SpaceId::generate(), 'Desk', 'Asia/Kolkata', 1, Money::of(1_000, Currency::BRL)); // UTC+05:30
    $hourly = new AlignedToGrid(60);

    // 10:00-11:00 in Kolkata is 04:30-05:30 UTC: on the local hourly grid, off the UTC one.
    expect($hourly->isSatisfiedBy(bookingRequest('2026-11-03T04:30:00Z', '2026-11-03T05:30:00Z', $kolkata)))->toBeTrue()
        ->and($hourly->isSatisfiedBy(bookingRequest('2026-11-03T04:00:00Z', '2026-11-03T05:00:00Z', $kolkata)))->toBeFalse()
        ->and((new AlignedToGrid(15))->isSatisfiedBy(bookingRequest('2026-11-03T10:15:30Z', '2026-11-03T11:00:00Z')))->toBeFalse();
});

it('counts the grid from midnight, so grids that do not divide an hour work too', function (): void {
    $grid = new AlignedToGrid(45);

    // 10:30-11:15 in São Paulo: 630 and 675 minutes after midnight, both multiples of 45.
    expect($grid->isSatisfiedBy(bookingRequest('2026-11-03T13:30:00Z', '2026-11-03T14:15:00Z')))->toBeTrue()
        ->and($grid->isSatisfiedBy(bookingRequest('2026-11-03T13:45:00Z', '2026-11-03T14:30:00Z')))->toBeFalse();
});

it('requires the whole slot to be within opening hours', function (string $start, string $end, bool $ok): void {
    $space = Builders::space(openingHours: new OpeningHours(WeeklyWindow::weekdays('08:00', '22:00')));

    expect((new WithinOpeningHours)->isSatisfiedBy(bookingRequest($start, $end, $space)))->toBe($ok);
})->with([
    'inside (Tue 09:00-10:00 local)' => ['2026-11-03T12:00:00Z', '2026-11-03T13:00:00Z', true],
    'runs past closing' => ['2026-11-03T00:00:00Z', '2026-11-03T01:30:00Z', false],
    'Saturday' => ['2026-11-07T12:00:00Z', '2026-11-07T13:00:00Z', false],
]);

it('caps upcoming bookings per customer', function (): void {
    $limit = new BelowActiveBookingLimit(3);

    expect($limit->isSatisfiedBy(bookingRequest('2026-11-03T10:00:00Z', '2026-11-03T11:00:00Z', active: 2)))->toBeTrue()
        ->and($limit->isSatisfiedBy(bookingRequest('2026-11-03T10:00:00Z', '2026-11-03T11:00:00Z', active: 3)))->toBeFalse();
});

describe('composition', function (): void {
    beforeEach(function (): void {
        $this->notice = new StartsWithMinimumNotice(60);
        $this->short = new DurationBetween(30, 60);
        $this->tooSoonAndTooLong = bookingRequest('2026-11-02T12:30:00Z', '2026-11-02T15:00:00Z');
        $this->fine = bookingRequest('2026-11-03T10:00:00Z', '2026-11-03T11:00:00Z');
    });

    it('combines rules with and(), reporting every violation', function (): void {
        $both = $this->notice->and($this->short);

        expect($both)->toBeInstanceOf(AllOf::class)
            ->and($both->isSatisfiedBy($this->fine))->toBeTrue()
            ->and($both->isSatisfiedBy($this->tooSoonAndTooLong))->toBeFalse()
            ->and($both->violationsOf($this->fine))->toBe([])
            ->and($both->violationsOf($this->tooSoonAndTooLong))->toBe([
                'Bookings must start at least 60 minutes from now.',
                'Bookings must last between 30 and 60 minutes.',
            ]);
    });

    it('accepts rules spread from a keyed array, such as configuration', function (): void {
        $rules = new AllOf(...['notice' => $this->notice, 'duration' => $this->short]);

        expect($rules->violationsOf($this->tooSoonAndTooLong))->toHaveCount(2)
            ->and($rules->isSatisfiedBy($this->fine))->toBeTrue();
    });

    it('combines rules with or()', function (): void {
        $either = $this->notice->or($this->short);
        $onlyLong = bookingRequest('2026-11-03T10:00:00Z', '2026-11-03T13:00:00Z');

        expect($either->isSatisfiedBy($onlyLong))->toBeTrue()
            ->and($either->violationsOf($this->tooSoonAndTooLong))->toBe([
                'One of: Bookings must start at least 60 minutes from now / Bookings must last between 30 and 60 minutes.',
            ]);
    });

    it('describes composed rules in words', function (): void {
        expect($this->notice->and($this->short)->describe())
            ->toBe('Bookings must start at least 60 minutes from now. Bookings must last between 30 and 60 minutes.')
            ->and($this->short->not()->describe())->toBe('Not: Bookings must last between 30 and 60 minutes.');
    });

    it('negates rules with not()', function (): void {
        $notShort = $this->short->not();

        expect($notShort->isSatisfiedBy($this->fine))->toBeFalse()
            ->and($notShort->isSatisfiedBy($this->tooSoonAndTooLong))->toBeTrue();
    });

    it('raises one exception listing all violations', function (): void {
        $rules = new BookingRules($this->notice->and($this->short));

        $rules->assertSatisfiedBy($this->fine);

        expect(fn () => $rules->assertSatisfiedBy($this->tooSoonAndTooLong))->toThrow(
            BookingRulesViolated::class,
            'Bookings must start at least 60 minutes from now. Bookings must last between 30 and 60 minutes.',
        );
    });
});
