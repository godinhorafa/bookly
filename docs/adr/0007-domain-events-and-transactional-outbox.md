# ADR 0007: Domain events delivered through a transactional outbox

- **Status:** Accepted
- **Date:** 2026-10-24

## Context

When a booking is requested, confirmed or cancelled, several things should
happen that are not part of the booking itself: an audit trail entry, an
e-mail to the customer, webhooks to partners, and later a real-time
notification service. Calling them from the use case would couple booking
logic to every channel and make a failing e-mail server break bookings.

Dispatching an event *after* the transaction commits is not enough either. If
the process dies between the commit and the dispatch, the event is lost. If we
dispatch *before* commit and the transaction rolls back, subscribers react to
something that never happened.

## Decision

- **Aggregates record domain events** (`booking.requested`,
  `booking.confirmed`, `booking.cancelled`) as their state changes. Events are
  immutable value objects with a stable wire name and a scalar payload.
- **Transactional outbox.** Use cases release the events and append them to the
  `outbox` table **in the same database transaction** as the aggregate. The
  event exists if and only if the change was committed. A test proves a
  rollback leaves no event behind.
- **Relay with observers.** `php artisan outbox:relay` (scheduled every minute)
  hands each event to every interested `EventSubscriber` (Observer pattern):
  - `AuditTrailSubscriber`: append-only `audit_log`, keyed by event id
  - `BookingConfirmationEmailSubscriber`: e-mail to the customer
  - `WebhookSubscriber`: one per endpoint, HMAC-signed like Stripe
    (`Bookly-Signature: t=…,v1=…`) with a `Bookly-Event-Id` for deduplication
- **At-least-once, tracked per subscriber.** Each successful `(event,
  subscriber)` pair is stored in `event_deliveries`, so when a webhook fails
  the retry calls only the webhook, and the customer never gets the e-mail twice.
  Failures back off exponentially (30 s, 1 min, 2 min … capped at 1 h) and are
  parked as *dead* after 8 attempts, with the last error kept for inspection.
- **Ordered per aggregate.** An event is only due once every earlier event of
  the same booking has been published or parked. Subscribers never see
  `booking.confirmed` before `booking.requested`, even when the first one had
  to be retried. A dead event stops blocking, so one poison message can't
  freeze a booking's stream forever.
- **Safe with several relays.** Claiming uses `UPDATE … WHERE id IN (SELECT …
  FOR UPDATE SKIP LOCKED) RETURNING …` and pushes `available_at` forward as a
  lease. Concurrent relays never take the same event, and a crashed relay's
  lease simply expires.

## Consequences

- Booking logic doesn't know who listens. A new reaction is one subscriber
  class plus one line in `InfrastructureServiceProvider`.
- Delivery is *at least once*, so subscribers must be idempotent (audit keys on
  the event id, webhook receivers get `Bookly-Event-Id`). E-mail can't be made
  strictly idempotent; per-subscriber tracking keeps duplicates to the rare
  case of a crash between sending and recording.
- With only the schedule, events reach subscribers up to a minute late.
  `outbox:relay --watch` polls continuously instead (see ADR 0008).
- The same outbox feeds the NestJS real-time service, as one more webhook
  endpoint, without changing the use cases ([ADR 0008](0008-realtime-service-fed-by-signed-webhooks.md)).
- Timestamps in these tables keep microseconds. With Laravel's default
  precision of 0, PostgreSQL *rounds* `12:00:00.6` up to `12:00:01`, so a
  freshly appended event would not be due for up to half a second. The
  end-to-end test caught this.
