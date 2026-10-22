<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class BookingConfirmedMail extends Mailable
{
    /**
     * @param  array<string, scalar|null>  $booking  payload of the booking.confirmed event
     */
    public function __construct(public readonly array $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your booking is confirmed');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.booking-confirmed');
    }
}
