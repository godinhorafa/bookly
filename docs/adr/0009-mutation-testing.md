# ADR 0009: Mutation testing gates the domain and use cases

- **Status:** Accepted
- **Date:** 2026-10-26

## Context

Line coverage says which code ran during the tests, not whether a test would
notice if that code were wrong. A test that calls `Coupon::percentage('X', 100)`
without checking the result covers the guard `$percent > 100`, but also passes
if the guard says `>= 100`.

Mutation testing makes that measurable. It changes the code one small way at a
time (`>` into `>=`, `+ 1` into `+ 2`, dropping a method call) and runs the
tests against each change. Every change the tests don't catch (a *surviving
mutant*) is a bug they would miss.

## Decision

- **Pest's built-in mutation testing** (`pest --mutate`). Infection 0.35 only
  supports PHPUnit, phpspec, Codeception and Testo test frameworks; our tests
  are written in Pest.
- **Scope: `src/Domain` and `src/Application`**, the business rules and use
  cases, run against the **unit suite** only, so it needs no database and
  takes about a minute. Infrastructure is covered by integration and contract
  tests, which would make each mutant run against PostgreSQL.
- **Every mutant must be killed** (`--min=100`), including mutants on lines no
  unit test covers (no `--covered-only`). CI runs `composer test:mutate` in its
  own job.
- **Ignoring a mutant is an exception, written next to the code.**
  `@pest-mutate-ignore` is allowed only for mutants that can't change behaviour,
  with a comment saying why:
  - reading a neighbouring random byte in `Uuid::generate`;
  - casts PHP would do anyway (`"10" * 60`);
  - class constants, which line coverage never marks as executed. Their values
    are pinned by tests. Changing the constants by hand was verified to fail
    those tests.
- **Coverage gate:** at least 95% of the whole suite, in CI and in
  `composer test:coverage`.

## What the first run found

The first run scored **82.3%**: 130 of 734 mutants survived. Most survivors
were real gaps:

- No test pinned the **payload of domain events**, which webhooks and the
  realtime service depend on. A field could disappear unnoticed.
- `PayForBookingHandler` could **stop recording `booking.confirmed` for a
  fully discounted booking** without a unit test failing.
- **Attempt numbers** (which feed payment idempotency keys) could start at 0 or
  2 unnoticed.
- **Boundaries** of percentages (0, 1, 100, 101), refund tiers (exactly 7 days,
  one second short of 24 h) and the 62-day availability range were untested.
- **Without its `instanceof` check, a coupon with no expiry date would read as
  expired**, and no test covered that case.
- `AllOf::isSatisfiedBy` could **always return true**: tests only checked its
  violation messages.
- Rules or opening hours spread from a **keyed array** (as configuration would
  produce) would crash. `array_values()` was needed, but no test showed why.
- Two `assertSameCurrency()` calls could be removed: mixing BRL and USD in
  `subtract()` or `greaterThan()` was untested.
- The **read-side queries** were only tested through HTTP.

Some survivors pointed at code to simplify rather than tests to add. These
were redundant `array_values()` calls on lists that are only iterated, an
`array_reverse()` over payments where only one can succeed, a `setTime()`
whose result was overwritten, and string arithmetic replaced by a
`DateInterval`.

The unit suite went from 221 to 265 tests.

## Consequences

- A rule change that is not pinned by a test fails CI, even when coverage is
  already 100%.
- New domain code needs tests that check results, not just execute lines.
- A new Pest mutator can fail the build after a dependency update. That is
  intended: it is a new kind of bug the tests should catch.
