import { describe, expect, it } from 'vitest';
import { signedExample, webhookBody } from '../../test/support/events.js';
import { MalformedEvent, parseBookingEvent } from './booking-event.js';

describe('parseBookingEvent', () => {
  it('reads the events the API publishes', () => {
    const event = parseBookingEvent(JSON.parse(signedExample.body));

    expect(event).toMatchObject({
      type: 'booking.requested',
      occurredAt: '2026-10-26T12:00:00+00:00',
      data: { customer_id: 1, start: '2026-10-28T18:00:00+00:00', price_amount: 9000 },
    });
  });

  it.each(['booking.requested', 'booking.confirmed', 'booking.cancelled'])('accepts %s', (type) => {
    expect(parseBookingEvent(webhookBody(type))?.type).toBe(type);
  });

  it('ignores event types it does not know, so the API can add new ones', () => {
    expect(parseBookingEvent(webhookBody('space.renamed'))).toBeNull();
  });

  it.each([
    ['not an object', []],
    ['no type', { id: '1' }],
    ['no id', { ...webhookBody('booking.requested'), id: undefined }],
    ['no data', { ...webhookBody('booking.requested'), data: null }],
    ['a customer id that is not an integer', webhookBody('booking.requested', { customer_id: '7' })],
    ['an end that is not a timestamp', webhookBody('booking.cancelled', { end: 'tomorrow' })],
    ['the amount of another event', webhookBody('booking.cancelled', { refund_amount: undefined, paid_amount: 1 })],
  ])('rejects a body with %s', (_case, body) => {
    expect(() => parseBookingEvent(body)).toThrow(MalformedEvent);
  });
});
