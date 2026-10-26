import { BadRequestException, Body, Controller, Get, HttpCode, HttpStatus, Post, UseGuards } from '@nestjs/common';
import { MalformedEvent, parseBookingEvent, type BookingEvent } from '../events/booking-event.js';
import { BookingEvents } from '../events/booking-events.js';
import { BooklySignatureGuard } from './bookly-signature.guard.js';

@Controller()
export class WebhooksController {
  constructor(private readonly events: BookingEvents) {}

  /**
   * Bookly's outbox relay posts every booking.* event here (WEBHOOK_ENDPOINTS on
   * the API). Any non-2xx answer makes the relay retry later.
   */
  @Post('webhooks/bookly')
  @HttpCode(HttpStatus.ACCEPTED)
  @UseGuards(BooklySignatureGuard)
  receive(@Body() body: unknown): { pushed: boolean } {
    let event: BookingEvent | null;
    try {
      event = parseBookingEvent(body);
    } catch (error) {
      if (error instanceof MalformedEvent) throw new BadRequestException(error.message);
      throw error;
    }

    return { pushed: event !== null && this.events.publish(event) };
  }

  @Get('health')
  health(): { status: 'ok' } {
    return { status: 'ok' };
  }
}
