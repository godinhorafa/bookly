# ADR 0004: Pricing rules and cancellation policies as strategies

- **Status:** Accepted
- **Date:** 2026-10-21

## Context

Prices depend on several independent concerns: peak hours, long-booking
discounts and coupons, with more to come (recurring series, member tiers).
Refunds depend on a policy the space owner picks. A single `calculatePrice()`
method with a growing chain of `if`s would mix every rule, make each one hard
to test alone, and force an edit to that method for every new rule
(Open/Closed violation).

## Decision

### Pricing

- Each concern is a **`PricingRule` strategy** (`PeakHourSurcharge`,
  `LongBookingDiscount`, `CouponDiscount`) with one method:
  `apply(Quote, PricingContext): Quote`.
- **`PricingPipeline`** starts from the space's prorated base price and applies
  the rules **in a fixed, explicit order**, configured in
  `InfrastructureServiceProvider` from `config/pricing.php`:
  1. surcharges (peak hours)
  2. percentage discounts (long booking)
  3. the customer's coupon

  Order changes the price. With a 25% peak surcharge, 10% long-booking
  discount and a BRL 50 coupon on a BRL 240 booking, this order gives
  **BRL 220**, while the reverse order gives **BRL 231**. A test in
  `PricingPipelineTest` documents both. Discounts apply to the surcharged
  amount, and the coupon comes last, so its value is predictable to the customer.
- A **`Quote`** keeps every `Adjustment` line, not just the total, so the API can
  show the customer *why* they pay what they pay.
- Peak windows are defined in **local wall-clock time** and evaluated in each
  space's time zone, so "18:00–22:00" stays 18:00–22:00 across DST changes.
  The surcharge applies only to the minutes that fall inside a window.
- Coupon validity (existence, expiry) is checked by the use case *before*
  pricing, through the `Clock` port. Rules never read the system time.

### Cancellation

- **`CancellationPolicy`** is a strategy: `refundFor(paid, slot, cancelledAt)`.
- `TieredRefundPolicy` covers the common case: "cancel at least N hours before
  and get P% back". It picks the most generous tier still met, and refunds
  nothing once the slot has started.
- Owners choose a **`CancellationPolicyType`** per space (flexible, moderate,
  strict, non-refundable). The enum maps each choice to its strategy, so adding
  a policy is one enum case plus one `match` arm.
- `Booking::cancel()` decides *whether* there is anything to refund (only
  confirmed, i.e. paid, bookings are). The policy decides *how much*.

## Consequences

- Each rule and policy is unit tested in isolation, with no framework or database.
- New rules don't modify existing ones, but adding one means choosing its
  position in the pipeline. That decision is visible in a single place.
- Rules assume peak windows don't overlap. Overlapping windows would charge the
  shared minutes twice. This is documented on `PeakHourSurcharge`, not enforced.
- The database mirrors the invariants with `CHECK` constraints (a cancelled
  booking must have a timestamp and a refund no greater than the price;
  coupon types and values), as a second line of defence.
