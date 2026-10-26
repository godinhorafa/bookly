<?php

declare(strict_types=1);

use Domain\Booking\BookingId;
use Domain\Booking\CustomerId;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Space\SpaceId;

it('generates valid, unique v4 identifiers', function (): void {
    $ids = array_map(static fn (): string => BookingId::generate()->value, range(1, 500));

    expect(array_unique($ids))->toHaveCount(500)
        ->and($ids[0])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('normalises case when parsing', function (): void {
    $id = SpaceId::fromString('0B9E7E4C-2F4B-4C1A-9D3E-7A1B2C3D4E5F');

    expect($id->value)->toBe('0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e5f')
        ->and((string) $id)->toBe($id->value);
});

it('rejects malformed identifiers with a message naming the type', function (): void {
    expect(fn (): SpaceId => SpaceId::fromString('not-a-uuid'))->toThrow(function (InvalidIdentifier $e): void {
        expect($e->getMessage())->toBe('SpaceId must be a UUID, got "not-a-uuid".');
    });
});

it('keeps every random bit random', function (): void {
    // Each hex digit outside the version and variant bits must take every value.
    $ids = array_map(static fn (): string => str_replace('-', '', BookingId::generate()->value), range(1, 2_000));

    foreach ([13, 17] as $digit) { // after the "4" version digit and the variant digit
        expect(array_unique(array_map(static fn (string $id): string => $id[$digit], $ids)))->toHaveCount(16);
    }
    expect(array_values(array_unique(array_map(static fn (string $id): string => $id[16], $ids))))->toEqualCanonicalizing(['8', '9', 'a', 'b']);
});

it('never treats ids of different aggregates as equal', function (): void {
    $value = '0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e5f';

    expect(SpaceId::fromString($value)->equals(SpaceId::fromString($value)))->toBeTrue()
        ->and(SpaceId::fromString($value)->equals(BookingId::fromString($value)))->toBeFalse();
});

it('requires a positive customer id', function (): void {
    new CustomerId(0);
})->throws(InvalidIdentifier::class, 'CustomerId must be a positive integer');
