import { Inject, Injectable, Logger } from '@nestjs/common';
import type { BookingEvent } from './booking-event.js';
import { fanOut, type Message } from './fan-out.js';
import { RecentEvents } from './recent-events.js';

export const BROADCASTER = Symbol('Broadcaster');

/** Port: delivers a message to everyone in a room (the WebSocket gateway in production). */
export interface Broadcaster {
  send(message: Message): void;
}

@Injectable()
export class BookingEvents {
  private readonly logger = new Logger(BookingEvents.name);
  private readonly recent = new RecentEvents();

  constructor(@Inject(BROADCASTER) private readonly broadcaster: Broadcaster) {}

  /** Pushes an event to the dashboards that should hear about it. False for a redelivery. */
  publish(event: BookingEvent): boolean {
    if (!this.recent.firstTime(event.id)) {
      this.logger.debug(`Skipped redelivered ${event.type} ${event.id}`);
      return false;
    }

    for (const message of fanOut(event)) {
      this.broadcaster.send(message);
    }
    this.logger.log(`Pushed ${event.type} for booking ${event.data.booking_id}`);

    return true;
  }
}
