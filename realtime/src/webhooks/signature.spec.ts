import { createHmac } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { signedExample } from '../../test/support/events.js';
import { verifySignature } from './signature.js';

const { secret, timestamp, body, header } = signedExample;

describe('verifySignature', () => {
  it('accepts the signature produced by the PHP side of Bookly', () => {
    expect(verifySignature(body, header, secret, timestamp + 60)).toBe(true);
    expect(verifySignature(Buffer.from(body), header, secret, timestamp)).toBe(true);
  });

  it.each([
    ['a tampered body', body.replace('9000', '1'), header, secret, timestamp],
    ['another secret', body, header, 'whsec_other', timestamp],
    ['a replay after the tolerance', body, header, secret, timestamp + 301],
    ['a timestamp from the future', body, header, secret, timestamp - 301],
    ['no header', body, undefined, secret, timestamp],
    ['a malformed header', body, 'v1=abc', secret, timestamp],
  ])('rejects %s', (_case, payload, signature, key, now) => {
    expect(verifySignature(payload, signature, key, now)).toBe(false);
  });

  it('rejects a valid HMAC moved to another timestamp', () => {
    const forged = header.replace(`t=${timestamp}`, `t=${timestamp + 10}`);

    expect(verifySignature(body, forged, secret, timestamp)).toBe(false);
  });

  it('signs "<timestamp>.<body>", like Stripe', () => {
    const v1 = createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex');

    expect(header).toBe(`t=${timestamp},v1=${v1}`);
  });
});
