import type { BookingEvent } from './booking-event.js';

/** Every signed-in dashboard joins this room. */
export const EVERYONE = 'customers';

/** Each customer's dashboards join their own room. */
export const customerRoom = (customerId: number): string => `customer:${customerId}`;

/**
 * What every signed-in customer learns: a slot of a space was taken or freed.
 * Nothing about who booked it.
 */
export interface SlotChanged {
  readonly space_id: string;
  readonly start: string;
  readonly end: string;
  readonly busy: boolean;
}

/** What the booking's owner learns. */
export interface BookingChanged {
  readonly event_id: string;
  readonly type: BookingEvent['type'];
  readonly booking_id: string;
  readonly space_id: string;
  readonly start: string;
  readonly end: string;
  readonly status: 'pending' | 'confirmed' | 'cancelled';
}

export type Message =
  | { readonly room: string; readonly name: 'slot.changed'; readonly payload: SlotChanged }
  | { readonly room: string; readonly name: 'booking.changed'; readonly payload: BookingChanged };

const STATUS: Record<BookingEvent['type'], BookingChanged['status']> = {
  'booking.requested': 'pending',
  'booking.confirmed': 'confirmed',
  'booking.cancelled': 'cancelled',
};

/**
 * Decides who hears about an event and what they are told. Pure, so the
 * privacy rules are tested without sockets.
 */
export function fanOut(event: BookingEvent): Message[] {
  const { booking_id, space_id, customer_id, start, end } = event.data;
  const messages: Message[] = [];

  // Confirming changes nothing for others: a pending booking already holds its slot.
  if (event.type !== 'booking.confirmed') {
    messages.push({
      room: EVERYONE,
      name: 'slot.changed',
      payload: { space_id, start, end, busy: event.type === 'booking.requested' },
    });
  }

  messages.push({
    room: customerRoom(customer_id),
    name: 'booking.changed',
    payload: { event_id: event.id, type: event.type, booking_id, space_id, start, end, status: STATUS[event.type] },
  });

  return messages;
}
