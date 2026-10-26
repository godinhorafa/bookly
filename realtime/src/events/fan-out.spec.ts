import { describe, expect, it } from 'vitest';
import { webhookBody } from '../../test/support/events.js';
import { parseBookingEvent, type BookingEvent } from './booking-event.js';
import { fanOut } from './fan-out.js';

const event = (type: string): BookingEvent => parseBookingEvent(webhookBody(type, {}, 'evt-1')) as BookingEvent;

describe('fanOut', () => {
  it('tells everyone a slot was taken, and the customer that the booking is pending', () => {
    expect(fanOut(event('booking.requested'))).toEqual([
      {
        room: 'customers',
        name: 'slot.changed',
        payload: { space_id: '0199a7d2-0000-7000-8000-0000000000aa', start: '2026-10-28T18:00:00+00:00', end: '2026-10-28T19:00:00+00:00', busy: true },
      },
      {
        room: 'customer:7',
        name: 'booking.changed',
        payload: {
          event_id: 'evt-1',
          type: 'booking.requested',
          booking_id: '0199a7d2-0000-7000-8000-000000000001',
          space_id: '0199a7d2-0000-7000-8000-0000000000aa',
          start: '2026-10-28T18:00:00+00:00',
          end: '2026-10-28T19:00:00+00:00',
          status: 'pending',
        },
      },
    ]);
  });

  it('frees the slot for everyone when a booking is cancelled', () => {
    const [slot, own] = fanOut(event('booking.cancelled'));

    expect(slot).toMatchObject({ room: 'customers', name: 'slot.changed', payload: { busy: false } });
    expect(own).toMatchObject({ room: 'customer:7', payload: { status: 'cancelled' } });
  });

  it('only tells the customer about a confirmation: the slot was already taken', () => {
    expect(fanOut(event('booking.confirmed'))).toMatchObject([{ room: 'customer:7', payload: { status: 'confirmed' } }]);
  });

  it('never shares who booked, the booking id or the amount with other customers', () => {
    for (const type of ['booking.requested', 'booking.confirmed', 'booking.cancelled']) {
      for (const message of fanOut(event(type)).filter((m) => m.room === 'customers')) {
        expect(Object.keys(message.payload).sort()).toEqual(['busy', 'end', 'space_id', 'start']);
      }
    }
  });
});
