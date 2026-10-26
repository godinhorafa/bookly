/**
 * The booking events Bookly publishes (Domain\Booking\Event in the API), as
 * they arrive in a webhook body. Wire format: docs/api/webhooks.md.
 */
interface Envelope<Type extends string, Data> {
  readonly id: string;
  readonly type: Type;
  readonly occurredAt: string;
  readonly data: Data;
}

interface BookingData {
  readonly booking_id: string;
  readonly space_id: string;
  readonly customer_id: number;
  /** ISO 8601, half-open [start, end). */
  readonly start: string;
  readonly end: string;
  readonly currency: string;
}

export type BookingRequested = Envelope<'booking.requested', BookingData & { readonly price_amount: number }>;
export type BookingConfirmed = Envelope<'booking.confirmed', BookingData & { readonly paid_amount: number }>;
export type BookingCancelled = Envelope<'booking.cancelled', BookingData & { readonly refund_amount: number }>;
export type BookingEvent = BookingRequested | BookingConfirmed | BookingCancelled;

export class MalformedEvent extends Error {}

/** The amount each event carries, in minor units. */
const AMOUNT_FIELD: Record<BookingEvent['type'], string> = {
  'booking.requested': 'price_amount',
  'booking.confirmed': 'paid_amount',
  'booking.cancelled': 'refund_amount',
};

const isKnownType = (type: string): type is BookingEvent['type'] => Object.hasOwn(AMOUNT_FIELD, type);

/**
 * Validates a webhook body. Returns null for event types this service does not
 * react to, so the API can publish new ones without breaking it.
 */
export function parseBookingEvent(body: unknown): BookingEvent | null {
  if (!isRecord(body)) throw new MalformedEvent('The body must be a JSON object.');
  if (typeof body['type'] !== 'string') throw new MalformedEvent('"type" must be a string.');

  const type = body['type'];
  if (!isKnownType(type)) return null;

  const { id, occurred_at: occurredAt, data } = body;
  requireString(id, 'id');
  requireTimestamp(occurredAt, 'occurred_at');
  if (!isRecord(data)) throw new MalformedEvent('"data" must be an object.');

  for (const key of ['booking_id', 'space_id', 'currency']) requireString(data[key], `data.${key}`);
  for (const key of ['start', 'end']) requireTimestamp(data[key], `data.${key}`);
  for (const key of ['customer_id', AMOUNT_FIELD[type]]) requireInteger(data[key], `data.${key}`);

  // Every field the union declares was checked above.
  return { id, type, occurredAt, data } as unknown as BookingEvent;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function requireString(value: unknown, field: string): asserts value is string {
  if (typeof value !== 'string' || value === '') throw new MalformedEvent(`"${field}" must be a non-empty string.`);
}

function requireTimestamp(value: unknown, field: string): asserts value is string {
  requireString(value, field);
  if (Number.isNaN(Date.parse(value))) throw new MalformedEvent(`"${field}" must be an ISO 8601 timestamp.`);
}

function requireInteger(value: unknown, field: string): asserts value is number {
  if (!Number.isInteger(value)) throw new MalformedEvent(`"${field}" must be an integer.`);
}
