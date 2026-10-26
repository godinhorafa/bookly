# ADR 0010: Unpaid bookings expire and give their slot back

- **Status:** Accepted
- **Date:** 2026-10-26

## Context

A booking is created as `pending` and holds its slot until it is paid, which
is what prevents two people paying for the same court. Before this change, a
pending booking held the slot **forever**. A customer who closed the tab at
checkout blocked that slot for everyone, and nothing ever released it.

Releasing a slot must not race with a payment. If the expiry job freed a slot
while a charge for it was in flight, the customer could be charged for a
booking that had already expired. Another customer might even have taken the
slot by then.

## Decision

- **Every booking gets a payment deadline**, `holdUntil`, set when it is
  requested: 15 minutes later by default (`BOOKING_PAYMENT_HOLD_MINUTES`,
  `PaymentHold`). It is stored in `bookings.hold_until` and shown to the
  customer ("Pay by 15:12") as `hold_until` in the API.
- **A new terminal status, `expired`.** `Booking::expire()` only works on a
  pending booking whose deadline has passed, and records `booking.expired`.
  Expired bookings don't hold their slot: the exclusion constraint now applies
  to `status IN ('pending', 'confirmed')`. Domain and database still agree, and
  a test checks it (`BookingStatus::holdsSlot()`).
- **Payment and expiry can't overlap:**
  - Payment is refused once the deadline has passed. This is checked
    *before* the gateway is called, so nobody is charged for a booking that
    is about to expire (`409 invalid-state`).
  - The expiry job (`bookings:expire`, every minute) only expires bookings
    whose deadline passed more than **2 minutes** ago. That is longer than a
    charge can take, since the Stripe adapter times out after 15 seconds.
  - So a payment that started before the deadline has always been saved
    before its booking can expire. No lock is held across the call to the
    payment provider.
- **The event flows like any other.** `booking.expired` goes through the
  outbox to the audit trail, the webhooks and the realtime service, so open
  dashboards show the slot as free again without a reload.

### Alternatives considered

- **Expire on read** (treat a pending booking past its deadline as free in
  queries). There is no job, but every query, the exclusion constraint and the
  per-customer limit would have to know about the clock. Nothing would record
  that the booking expired, and no event would tell anyone.
- **Lock the booking row during payment** (`SELECT … FOR UPDATE` around the
  charge). This is correct, but it keeps a database transaction open while
  waiting on Stripe. A short deadline check plus a grace period gives the same
  guarantee without that.
- **Optimistic locking** (a version column). It would also protect other
  concurrent updates, such as a cancel racing a payment. It is worth adding if
  those races show up; for expiry, the time-based guarantee is enough and
  simpler to explain.

## Consequences

- A slot abandoned at checkout comes back after at most the hold, plus the
  2-minute grace, plus one scheduler minute.
- `docker compose` runs a `scheduler` service (`schedule:work`), so expiry
  happens locally too.
- The grace period depends on the payment adapters' timeouts. A slower
  adapter needs a longer grace period: `ExpireUnpaidBookingsHandler::GRACE_SECONDS`
  documents this next to the value.
- Existing pending bookings were given a deadline of `created_at + 15 minutes`
  by the migration.
