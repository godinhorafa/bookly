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

it('rejects malformed identifiers with a typed message', function (): void {
    SpaceId::fromString('not-a-uuid');
})->throws(InvalidIdentifier::class, 'SpaceId must be a UUID');

it('never treats ids of different aggregates as equal', function (): void {
    $value = '0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e5f';

    expect(SpaceId::fromString($value)->equals(SpaceId::fromString($value)))->toBeTrue()
        ->and(SpaceId::fromString($value)->equals(BookingId::fromString($value)))->toBeFalse();
});

it('requires a positive customer id', function (): void {
    new CustomerId(0);
})->throws(InvalidIdentifier::class, 'CustomerId must be a positive integer');
