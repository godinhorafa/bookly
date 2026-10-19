# ADR 0003: Repositories over direct Eloquent, verified by contract tests

- **Status:** Accepted
- **Date:** 2026-10-19

## Context

Laravel encourages using Eloquent models everywhere: in controllers, jobs and
services. That is productive for CRUD, but here it would mean:

- business rules (status transitions, overlap rules) living on Active Record
  models, mixed with persistence concerns;
- use cases that can only be tested with a database;
- the domain depending on `Illuminate\Database`, which ADR 0001 forbids.

## Decision

- The domain owns **repository interfaces** (`BookingRepository`,
  `SpaceRepository`), phrased in domain terms: `get`, `save`, `findOverlapping`.
- `Infrastructure\Persistence\Eloquent` implements them. Eloquent models
  (`BookingModel`, `SpaceModel`) are **persistence-only**: no behaviour, and
  they never leave the infrastructure layer. An architecture test enforces that
  `Illuminate\Database` is only used in Infrastructure, App and database code.
- Repositories translate infrastructure failures into domain language. For
  example, SQLSTATE `23P01` on `bookings_no_overlap` becomes `SlotUnavailable`.
- Use cases are unit tested against **in-memory repositories**.
- To keep the in-memory fakes honest, one **contract test suite**
  (`tests/Contracts/BookingRepositoryContract.php`) runs against both
  implementations:
  - `tests/Unit/Contracts/InMemoryBookingRepositoryTest.php` (milliseconds, no I/O)
  - `tests/Integration/Persistence/EloquentBookingRepositoryTest.php` (real PostgreSQL)

  If the fake and the real adapter ever disagree, one of them fails.

## Consequences

- Use case tests are fast and need no database, yet they still match production behaviour.
- There is mapping code between models and aggregates, and a second implementation to
  maintain. The contract suite makes that cost visible and safe.
- The race between the use case's overlap check and the insert is covered by a
  separate test (`tests/Concurrency`) that runs real parallel processes, because
  no in-memory fake can prove transactional behaviour.
