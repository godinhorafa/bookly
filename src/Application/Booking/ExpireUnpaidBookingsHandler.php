<?php

declare(strict_types=1);

namespace Application\Booking;

use Application\Event\Outbox;
use Application\Transaction;
use Domain\Booking\BookingRepository;
use Domain\Shared\Clock;

/**
 * Gives back the slots of bookings that were not paid in time.
 *
 * A booking expires only once its hold ended more than GRACE_SECONDS ago. That is
 * longer than any charge can take (the gateway times out first), so a payment
 * that started before the deadline always finishes before its booking can expire.
 */
final readonly class ExpireUnpaidBookingsHandler
{
    // Pinned by ExpireUnpaidBookingsHandlerTest (constant lines never show as covered).
    public const int GRACE_SECONDS = 120; // @pest-mutate-ignore

    public function __construct(
        private BookingRepository $bookings,
        private Outbox $outbox,
        private Transaction $transaction,
        private Clock $clock,
    ) {}

    /**
     * @return int how many bookings expired
     */
    public function handle(int $limit): int
    {
        $now = $this->clock->now();
        $cutoff = $now->sub(new \DateInterval('PT' . self::GRACE_SECONDS . 'S'));
        $expired = 0;

        foreach ($this->bookings->pendingWithHoldEndedBefore($cutoff, $limit) as $booking) {
            $booking->expire($now);

            $this->transaction->run(function () use ($booking, $now): void {
                $this->bookings->save($booking);
                $this->outbox->append($now, ...$booking->releaseEvents());
            });
            $expired++;
        }

        return $expired;
    }
}
