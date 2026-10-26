import { createHmac, timingSafeEqual } from 'node:crypto';

const HEADER = /^t=(\d+),v1=([0-9a-f]{64})$/;

/**
 * Verifies a `Bookly-Signature` header: "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<body>">".
 * The counterpart of Infrastructure\Notification\WebhookSignature in the API; both are
 * tested against docs/api/webhook-signature.example.json.
 *
 * The body must be the exact bytes received: re-serialised JSON would not match.
 */
export function verifySignature(
  body: Buffer | string,
  header: string | undefined,
  secret: string,
  nowSeconds: number,
  toleranceSeconds = 300,
): boolean {
  const match = HEADER.exec(header ?? '');
  if (match === null) return false;

  const [, timestamp = '', signature = ''] = match;
  // Old signatures are rejected, so a captured request can't be replayed later.
  if (Math.abs(nowSeconds - Number(timestamp)) > toleranceSeconds) return false;

  const expected = createHmac('sha256', secret).update(`${timestamp}.`).update(body).digest();

  return timingSafeEqual(expected, Buffer.from(signature, 'hex'));
}
