<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Booking\CustomerId;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Space\SpaceRepository;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Eloquent\EloquentBookingRepository;
use Tests\Contracts\BookingRepositoryFixture;
use Tests\Support\Builders;

require_once __DIR__ . '/../../Contracts/BookingRepositoryContract.php';

bookingRepositoryContract(function (): BookingRepositoryFixture {
    $spaces = app(SpaceRepository::class);
    $space = Builders::space();
    $otherSpace = Builders::space(name: 'Tennis court');
    $spaces->save($space);
    $spaces->save($otherSpace);

    return new BookingRepositoryFixture(
        app(EloquentBookingRepository::class),
        $space->id,
        $otherSpace->id,
        new CustomerId(User::factory()->create()->id),
    );
});

it('keeps the surrounding transaction usable after a rejected booking', function (): void {
    $fx = $this->fx;
    $fx->repository->save(Builders::booking($fx->space, $fx->customer));

    try {
        $fx->repository->save(Builders::booking($fx->space, $fx->customer));
    } catch (SlotUnavailable) {
        // expected
    }

    // Without a savepoint PostgreSQL would answer "current transaction is aborted".
    $fx->repository->save(Builders::booking($fx->space, $fx->customer, '2026-11-03T10:00:00Z', '2026-11-03T11:00:00Z'));

    expect(DB::table('bookings')->count())->toBe(2);
});
