<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Application\Event\EventSubscriber;
use Application\Event\StoredEvent;
use Domain\Booking\Event\BookingConfirmed;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\ConnectionInterface;

/**
 * E-mails the customer when their booking is confirmed. Mail can't be made
 * strictly idempotent, but per-subscriber delivery tracking means a failure
 * elsewhere (say, a webhook) never causes the e-mail to be sent twice.
 */
final readonly class BookingConfirmationEmailSubscriber implements EventSubscriber
{
    public function __construct(private Mailer $mailer, private ConnectionInterface $db) {}

    public function name(): string
    {
        return 'email.booking-confirmed';
    }

    public function isInterestedIn(string $eventName): bool
    {
        return $eventName === BookingConfirmed::name();
    }

    public function handle(StoredEvent $event): void
    {
        $customer = $this->db->table('users')->where('id', $event->payload['customer_id'])->first(['name', 'email']);

        if ($customer === null) {
            return; // the account was deleted in the meantime: nobody to notify
        }

        $this->mailer->to($customer->email)->send(
            (new BookingConfirmedMail($event->payload))->with('customerName', $customer->name),
        );
    }
}
