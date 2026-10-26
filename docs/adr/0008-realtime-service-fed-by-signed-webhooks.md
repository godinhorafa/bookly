# ADR 0008: A NestJS real-time service fed by signed webhooks

- **Status:** Accepted
- **Date:** 2026-10-26

## Context

Two customers looking at the same day see stale calendars: the dashboard only
learns that a slot was taken when it refetches. Pushing changes needs a
long-lived WebSocket per dashboard, which PHP's request/response model handles
poorly and Node.js handles well.

The service must:

- hear about every booking change, without the booking code knowing it exists;
- tell every customer that a slot was taken or freed, but never who took it;
- tell a customer about their own bookings only;
- not become a second source of truth for bookings or tokens.

## Decision

A separate **NestJS** service (`realtime/`), with Socket.IO for the dashboards.

- **Fed by the existing webhooks.** It is one more `WEBHOOK_ENDPOINTS` entry.
  The outbox relay (ADR 0007) already gives at-least-once, per-booking ordered
  delivery with retries and dead letters. The service verifies the same HMAC
  signature a partner would (`BooklySignatureGuard`), over the raw body.
- **Dedupes redeliveries** by event id (`RecentEvents`, bounded, in memory).
- **Privacy in one pure function.** `fanOut` maps an event to messages:
  `slot.changed` (space, start, end, busy) to every signed-in dashboard, and
  `booking.changed` to the owner's room only. A test asserts the public
  message has no customer, booking or amount fields.
- **Messages are hints, not data.** Dashboards react by refetching from the
  API (TanStack Query invalidation), and refetch everything after reconnecting.
  A lost message costs freshness, never correctness.
- **Authentication through the API.** A dashboard connects with its Sanctum
  token. The gateway resolves it with `GET /api/me` during the handshake
  (`IdentityProvider` port, `BooklyApiIdentity` adapter). Unknown tokens never
  get a connection. When the API is unreachable, the connection fails as
  `unavailable` rather than `unauthenticated`.
- **Near real time.** `php artisan outbox:relay --watch` polls the outbox
  every 250 ms and exits cleanly on SIGTERM. The minute schedule remains as a
  safety net; `SKIP LOCKED` makes running both safe.
- **One contract, two languages.** `docs/api/webhook-signature.example.json`
  is signed by the PHP code and verified by the Node code in both test suites.

### Alternatives considered

- **Redis (pub/sub or streams) between Laravel and Nest.** It adds
  infrastructure that duplicates what the outbox already guarantees, and
  Laravel would need a Redis subscriber with its own retries. It becomes worth
  it with several Nest instances, through the Socket.IO Redis adapter (see
  Consequences).
- **Laravel Reverb or Pusher.** Fewer moving parts, but no Node.js, and the
  broadcast would happen inside the request rather than from the outbox.
- **Server-Sent Events.** These would be enough for one-way updates.
  Socket.IO adds reconnection and rooms out of the box, and the handshake
  `auth` keeps tokens out of URLs.

## Consequences

- No booking code changed. The cancellation event gained `start` and `end`,
  so listeners can free a slot without a lookup.
- The service is stateless apart from the dedupe window. With more than one
  instance, a dashboard connected to instance A must still hear webhooks
  received by B. That needs the Socket.IO Redis adapter: a configuration
  change, not a redesign.
- A token revoked while a socket is open keeps receiving slot updates until
  the socket reconnects. Slot updates carry no personal data, and logging out
  closes the socket.
- End to end, an update takes about the relay interval plus one HTTP hop.
  Playwright verifies it with two browsers: one books, the other's calendar
  changes without a reload.
