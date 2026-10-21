# ADR 0005: Booking rules as composable specifications

- **Status:** Accepted
- **Date:** 2026-10-22

## Context

Before a booking is accepted, the request has to pass a growing list of rules:
enough notice, not too far ahead, a sensible duration, aligned to the
calendar grid, within the space's opening hours, and under the customer's limit
of upcoming bookings. Written as a sequence of `if`s in the use case, these rules:

- stop at the first failure, so the customer fixes one problem only to hit the next;
- can't be reused or recombined (e.g. "members may book further ahead");
- make the use case grow with every new rule.

Chain of Responsibility was considered. It fits handlers that can *handle*
or *pass on* a request, but here every rule must be evaluated and the results
combined, which is what Specification is designed for.

## Decision

- Each rule is a **`BookingSpecification`** (`StartsWithMinimumNotice`,
  `WithinBookingHorizon`, `DurationBetween`, `AlignedToGrid`,
  `WithinOpeningHours`, `BelowActiveBookingLimit`) with `isSatisfiedBy()`,
  a human-readable `describe()` and `violationsOf()`.
- Specifications compose with **`and()`, `or()` and `not()`** (`AllOf`, `AnyOf`,
  `Not`). `AllOf` reports the violations of *every* failing part, so the API can
  return all problems in one response.
- Specifications are **pure**: they evaluate a `BookingRequest` that already
  carries the facts they need (the space, the slot, "now", the customer's count
  of upcoming bookings). The use case gathers those facts. No specification
  touches a repository or the clock.
- `BookingRules` holds the configured composition and throws
  `BookingRulesViolated` with the full list. The thresholds live in
  `config/booking.php` and are assembled in `InfrastructureServiceProvider`.
- Time-of-day rules (grid, opening hours) are evaluated in the **space's local
  time**. A 60-minute grid in `Asia/Kolkata` (UTC+05:30) means 10:00, 11:00…
  local, not on the UTC hour.
- Opening hours reuse the same `WeeklyWindow` as peak pricing, now in the
  shared kernel. A slot is accepted only if every one of its minutes is inside
  a window, so a 24/7 space accepts overnight bookings while a 08:00–22:00 one
  does not.

## Consequences

- A new rule is one small class plus one line in the provider. The use case doesn't change.
- Every rule is unit tested in isolation, including time-zone and DST edge cases.
- **The per-customer limit is a soft cap.** It is checked against a count read
  before the insert, so two simultaneous requests from the same customer could
  both pass. Unlike overbooking (ADR 0002), the cost of that race is low.
  Making it strict would need a per-customer lock or a counter row updated in
  the same transaction, which we will add if the limit ever becomes contractual.
