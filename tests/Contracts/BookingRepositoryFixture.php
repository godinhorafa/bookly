<?php

declare(strict_types=1);

namespace Tests\Contracts;

use Domain\Booking\BookingRepository;
use Domain\Booking\CustomerId;
use Domain\Space\SpaceId;

/**
 * What every BookingRepository implementation must provide to run the contract:
 * the repository plus two existing spaces and one existing customer.
 */
final readonly class BookingRepositoryFixture
{
    public function __construct(
        public BookingRepository $repository,
        public SpaceId $space,
        public SpaceId $otherSpace,
        public CustomerId $customer,
    ) {}
}
