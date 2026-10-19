<?php

declare(strict_types=1);

use Domain\Booking\CustomerId;
use Domain\Space\SpaceId;
use Tests\Contracts\BookingRepositoryFixture;
use Tests\Support\InMemory\InMemoryBookingRepository;

require_once __DIR__ . '/../../Contracts/BookingRepositoryContract.php';

bookingRepositoryContract(fn (): BookingRepositoryFixture => new BookingRepositoryFixture(
    new InMemoryBookingRepository,
    SpaceId::generate(),
    SpaceId::generate(),
    new CustomerId(1),
));
