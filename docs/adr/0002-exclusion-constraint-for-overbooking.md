# ADR 0002: PostgreSQL exclusion constraint to prevent overbooking

- **Status:** Accepted
- **Date:** 2026-10-18

## Context

Two customers can try to book the same space for overlapping times at the same
moment. A check-then-insert in application code (`SELECT ... WHERE overlaps`,
then `INSERT`) is a race condition: both requests can pass the check before
either one inserts.

Options considered:

1. **Application check only:** simple, but incorrect under concurrency.
2. **Pessimistic lock** (`SELECT ... FOR UPDATE` on the space row): correct,
   but it serialises every booking of a space, and every write path has to
   remember to take the lock.
3. **Exclusion constraint** on a `tstzrange` column: the database guarantees
   the invariant atomically, whatever code path writes the row.

## Decision

Use option 3:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE bookings
    ADD COLUMN period tstzrange NOT NULL,
    ADD CONSTRAINT bookings_period_half_open
        CHECK (NOT isempty(period) AND lower_inc(period) AND NOT upper_inc(period)),
    ADD CONSTRAINT bookings_no_overlap
        EXCLUDE USING gist (space_id WITH =, period WITH &&)
        WHERE (status <> 'cancelled');
```

- `btree_gist` allows the scalar `space_id` and the range in one GiST index.
- Ranges are **half-open `[)`**, so back-to-back bookings (10:00–11:00 and
  11:00–12:00) don't conflict. `TimeSlot::overlaps()` uses the same semantics,
  and a `CHECK` constraint rejects any other bound type.
- The `WHERE` clause makes the constraint **partial**: cancelled bookings
  release their slot.
- The use case still checks for overlaps up front to give a friendly error,
  but the constraint is the source of truth for the race. `EloquentBookingRepository`
  translates SQLSTATE `23P01` (`exclusion_violation`) into the domain
  `SlotUnavailable` exception.

## Consequences

- Correct under any concurrency, with no explicit locking in application code.
- Ties the persistence layer to PostgreSQL. This is acceptable because the
  domain stays database-agnostic and only the repository knows about ranges.
- The behaviour is covered by integration tests against a real PostgreSQL
  (`tests/Integration/Persistence/BookingOverlapConstraintTest.php`), not SQLite.
- `tests/Concurrency/ConcurrentBookingTest.php` starts 8 PHP processes that book
  the same slot at the same instant through the real use case. Exactly one succeeds.
  With the constraint disabled, the same test reliably lets 2–3 bookings through.
  That is the race this decision exists to prevent.
