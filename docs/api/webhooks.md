# Webhooks

Bookly posts every `booking.*` domain event to each URL in `WEBHOOK_ENDPOINTS`,
through the transactional outbox ([ADR 0007](../adr/0007-domain-events-and-transactional-outbox.md)).
The realtime service is one of these receivers ([ADR 0008](../adr/0008-realtime-service-fed-by-signed-webhooks.md)).

## Request

```http
POST /webhooks/bookly HTTP/1.1
Content-Type: application/json
Bookly-Event-Id: 0199a7d2-5b1e-7c3a-9f00-3c1e2d4b5a61
Bookly-Signature: t=1790000000,v1=5c23dec5…

{"id":"0199a7d2-…","type":"booking.requested","occurred_at":"2026-10-26T12:00:00+00:00","data":{…}}
```

| Event | `data` |
|---|---|
| `booking.requested` | `booking_id`, `space_id`, `customer_id`, `start`, `end`, `price_amount`, `currency` |
| `booking.confirmed` | `booking_id`, `space_id`, `customer_id`, `start`, `end`, `paid_amount`, `currency` |
| `booking.cancelled` | `booking_id`, `space_id`, `customer_id`, `start`, `end`, `refund_amount`, `currency` |
| `booking.expired` | `booking_id`, `space_id`, `customer_id`, `start`, `end` (not paid in time; the slot is free again) |

Amounts are in minor units. `start` and `end` are ISO 8601 instants of a half-open slot.
New event types may be added: receivers should accept and ignore types they don't know.

## Verifying the signature

`Bookly-Signature` is `t=<unix time>,v1=<hex HMAC-SHA256>`, where the HMAC is
computed with the shared `WEBHOOK_SECRET` over `"<t>.<raw body>"`:

1. Parse `t` and `v1`; reject anything else.
2. Reject if `t` is more than 5 minutes away from now (replays).
3. Compute the HMAC over the **raw** request body (not re-serialised JSON) and
   compare it to `v1` in constant time.

[`webhook-signature.example.json`](webhook-signature.example.json) is a signed
example. The PHP signer (`WebhookSignature`) and the Node.js verifier
(`realtime/src/webhooks/signature.ts`) are both tested against it, so the two
implementations can't drift apart.

## Delivery

- Any `2xx` answer means delivered. Anything else, or no answer within 10 s, is
  retried with exponential backoff (30 s up to 1 h), and parked as dead after 8 attempts.
- Delivery is **at least once**: deduplicate on `Bookly-Event-Id`.
- Events of one booking arrive in order.
