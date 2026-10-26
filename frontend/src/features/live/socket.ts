import { io } from 'socket.io-client';

/*
 * Messages from the realtime service (realtime/src/events/fan-out.ts). They are
 * hints to refetch, never data to display as-is: the API stays the source of truth.
 */

/** Sent to every signed-in dashboard. */
export interface SlotChanged {
  space_id: string;
  start: string;
  end: string;
  busy: boolean;
}

/** Sent only to the dashboards of the booking's owner. */
export interface BookingChanged {
  event_id: string;
  booking_id: string;
  status: 'pending' | 'confirmed' | 'cancelled' | 'expired';
}

export interface LiveSocket {
  on(event: 'connect' | 'disconnect' | 'connect_error', listener: () => void): unknown;
  on(event: 'slot.changed', listener: (message: SlotChanged) => void): unknown;
  on(event: 'booking.changed', listener: (message: BookingChanged) => void): unknown;
  disconnect(): unknown;
}

export type Connect = (token: string) => LiveSocket;

/** Same origin: the dev and preview servers proxy /socket.io to the realtime service. */
export const connectLive: Connect = (token) => io({ auth: { token }, transports: ['websocket'] }) as LiveSocket;
