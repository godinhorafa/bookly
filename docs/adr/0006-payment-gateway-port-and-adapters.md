# ADR 0006: Payment gateway as a port with adapters and a shared contract

- **Status:** Accepted
- **Date:** 2026-10-23

## Context

Bookings are confirmed once paid. We want to use Stripe in production, but
development, demos and almost every test must work without network access or
credentials. Leaking a provider's SDK types into the use cases would also make
a second provider (or a provider change) touch the core of the application.

Charging money also has failure modes that ordinary writes don't:

- a declined card is a *normal* outcome, not an error;
- a network timeout leaves us not knowing whether the charge happened;
- the database can fail *after* the provider took the money, and a rollback
  can't undo a charge.

## Decision

- **`PaymentGateway` is a domain port** (`charge`, `refund`) phrased in our
  terms: `Money`, a `ChargeResult` that is either *succeeded* or *declined*,
  `PaymentGatewayUnavailable` for retryable outages, and `RefundRejected`.
- **Adapters** translate providers into that port:
  - `StripePaymentGateway` calls the REST API (PaymentIntents, Refunds) through
    Laravel's HTTP client, with no SDK. It maps `402 card_error` to a decline,
    `5xx` and network errors to `PaymentGatewayUnavailable`, and refund `4xx`
    to `RefundRejected`.
  - `FakePaymentGateway` accepts **the same test payment methods as Stripe test
    mode** (`pm_card_visa`, `pm_card_chargeDeclined`, …) and returns the same
    decline codes, so switching `PAYMENT_GATEWAY` needs no other change.
- **One contract suite** (`tests/Contracts/PaymentGatewayContract.php`) runs
  against the fake, against the Stripe adapter with an offline
  `StripeEmulator`, and against **real Stripe test mode** when
  `STRIPE_TEST_SECRET` is set (locally or as a CI secret). It covers
  successful and declined charges, idempotent replays, and partial refunds up
  to the charged amount and no further.
- **Idempotency is designed in.** Every call carries a deterministic key:
  `booking-{id}-charge-{attempt}` and `booking-{id}-refund`. The gateway is
  called **outside** the database transaction. If saving fails after a
  successful charge, the retry uses the same attempt number and the provider
  replays the original charge instead of taking the money twice. A unit test
  simulates exactly this.
- Attempts are numbered explicitly and stored with `UNIQUE (booking_id, attempt)`.
  A partial unique index allows **at most one successful charge per booking**.
- Refunds go back to **the gateway that took the charge** (`PaymentGateways`
  registry), even if the default gateway has changed since.

## Consequences

- Use cases and their tests know nothing about Stripe.
- The emulator is only as faithful as our reading of Stripe's documentation.
  The live contract run is what keeps it honest, which is why it's wired into
  CI behind an optional secret.
- Pending bookings hold their slot until paid or cancelled. Expiring unpaid
  bookings after a few minutes is a follow-up.
- Only server-side confirmation is supported (`allow_redirects=never`), so 3-D
  Secure flows that need a redirect are out of scope for now.
