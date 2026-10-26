import { readFileSync } from 'node:fs';

/** The example signed by the PHP side (docs/api/webhook-signature.example.json). */
export const signedExample = JSON.parse(
  readFileSync(new URL('../../../docs/api/webhook-signature.example.json', import.meta.url), 'utf8'),
) as { secret: string; timestamp: number; body: string; header: string };

const SLOT = {
  booking_id: '0199a7d2-0000-7000-8000-000000000001',
  space_id: '0199a7d2-0000-7000-8000-0000000000aa',
  customer_id: 7,
  start: '2026-10-28T18:00:00+00:00',
  end: '2026-10-28T19:00:00+00:00',
  currency: 'BRL',
};

/** A webhook body as the API sends it. */
export function webhookBody(type: string, overrides: Record<string, unknown> = {}, id: string = crypto.randomUUID()): Record<string, unknown> {
  const amount = { 'booking.requested': { price_amount: 9_000 }, 'booking.confirmed': { paid_amount: 9_000 }, 'booking.cancelled': { refund_amount: 9_000 } }[type] ?? {};

  return { id, type, occurred_at: '2026-10-26T12:00:00+00:00', data: { ...SLOT, ...amount, ...overrides } };
}
