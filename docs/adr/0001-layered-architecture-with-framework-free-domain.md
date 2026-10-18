# ADR 0001: Layered architecture with a framework-free domain

- **Status:** Accepted
- **Date:** 2026-10-18

## Context

Booking rules (no overlapping slots, dynamic pricing, refund policies) are the
core value of the system and will change more often than the delivery
mechanism. If these rules live in controllers or Eloquent models, they become
hard to test in isolation and coupled to framework upgrades.

## Decision

The code is split into four layers, with dependencies pointing inwards only:

| Layer | Namespace | May depend on |
|---|---|---|
| Domain | `Domain\` (`src/Domain`) | nothing but PHP |
| Application | `Application\` (`src/Application`) | Domain |
| Infrastructure | `Infrastructure\` (`src/Infrastructure`) | Domain, Application, Laravel, PostgreSQL |
| Delivery (HTTP) | `App\` (`app/`) | all of the above |

- The domain uses immutable value objects (`Money`, `TimeSlot`) and plain PHP
  exceptions extending `Domain\Shared\Exception\DomainException`.
- Ports (for example `BookingRepository` and `PaymentGateway`) are interfaces in
  the inner layers. Laravel's container binds them to Infrastructure adapters.
- The rule is **enforced by tests**, not by convention: `tests/Arch/LayersTest.php`
  fails the build if `Domain` or `Application` imports `Illuminate`, `Carbon`,
  `App` or `Infrastructure`.

## Consequences

- Domain unit tests run without booting Laravel, so they are fast and deterministic.
- There is some mapping code (Eloquent row ↔ domain object) that a plain
  Active Record approach would avoid. We accept it in exchange for testability
  and a clear boundary.
- Carbon is intentionally banned from the domain; `DateTimeImmutable` is used instead.
