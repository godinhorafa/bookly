# Bookly

> A space booking API (coworking desks, meeting rooms, sports courts) built to
> show how to design a Laravel application around a framework-free domain: real
> business rules, design patterns where they make sense, and tests at every layer.

![CI](https://github.com/godinhorafa/bookly/actions/workflows/ci.yml/badge.svg)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4)
![PHPStan max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)
![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16-336791)

## Why this project

Booking is a small domain with non-trivial rules:

- **No overbooking**, even under concurrent requests
- **Dynamic pricing**: peak hours, recurring-booking discounts, coupons
- **Configurable cancellation policies**: 100% refund up to 48h before, 50% up to 24h, and so on
- **Multiple payment gateways** and **notifications** (e-mail, webhooks)

## Architecture

```mermaid
flowchart LR
    HTTP["App\\ (HTTP)<br/>controllers, requests, resources"] --> APP
    INFRA["Infrastructure\\<br/>Eloquent repositories, gateways, PostgreSQL"] --> APP
    APP["Application\\<br/>use cases, DTOs, ports"] --> DOMAIN
    INFRA --> DOMAIN
    DOMAIN["Domain\\<br/>entities, value objects, policies<br/><i>plain PHP, no framework</i>"]
```

Dependencies point inwards only. This is **enforced by architecture tests**
(`tests/Arch/LayersTest.php`): the build fails if the domain imports Laravel,
Carbon or an outer layer. See [ADR 0001](docs/adr/0001-layered-architecture-with-framework-free-domain.md).

### Highlights

| Concern | Approach |
|---|---|
| Repositories | Domain-owned ports, Eloquent adapters, `23P01` translated to `SlotUnavailable` ([ADR 0003](docs/adr/0003-repositories-with-contract-tests.md)) |
| Overbooking | PostgreSQL `EXCLUDE USING gist` on a half-open `tstzrange`, partial on `status <> 'cancelled'` ([ADR 0002](docs/adr/0002-exclusion-constraint-for-overbooking.md)) |
| Pricing | `PricingRule` strategies (peak hours in the space's local time, long-booking discount, coupons) composed by an ordered `PricingPipeline` that returns an itemised `Quote` ([ADR 0004](docs/adr/0004-pricing-and-cancellation-as-strategies.md)) |
| Cancellation | `CancellationPolicy` strategies chosen per space (flexible / moderate / strict / non-refundable), tiered refunds, time injected through a `Clock` port |
| Booking rules | Composable **Specifications** (`and` / `or` / `not`): notice, horizon, duration, 15-min grid and opening hours in the space's local time, per-customer cap. Every violation is reported at once ([ADR 0005](docs/adr/0005-booking-rules-as-specifications.md)) |
| Payments | `PaymentGateway` port with **Stripe** (REST, no SDK) and **Fake** adapters behind one contract suite that also runs against real Stripe test mode. Deterministic idempotency keys make retries safe ([ADR 0006](docs/adr/0006-payment-gateway-port-and-adapters.md)) |
| Domain events | Aggregates record events; a **transactional outbox** stores them with the change, and a relay delivers them (**Observer**) to audit, e-mail and HMAC-signed webhooks: at least once, tracked per subscriber, ordered per booking, safe with concurrent relays (`SKIP LOCKED`) ([ADR 0007](docs/adr/0007-domain-events-and-transactional-outbox.md)) |
| Money | `Money` value object in minor units with a `Currency` enum, so no floats |
| Time | `TimeSlot` value object, half-open `[start, end)`, normalised to UTC, DST-safe durations |
| Code style & safety | `declare(strict_types=1)` everywhere, `readonly` value objects, PHPStan **level max**, Pint, Rector |

## Testing strategy

| Suite | What it covers | Boots Laravel? | Database |
|---|---|---|---|
| `Unit` | Domain rules, value objects and use cases (with in-memory repositories) | No | None |
| `Arch` | Layer dependency rules, immutability, strict types | No | None |
| `Integration` | Repositories and DB constraints | Yes | **Real PostgreSQL** (not SQLite) |
| `Concurrency` | 8 parallel processes racing for one slot: exactly one wins | Yes | Real PostgreSQL |
| `Feature` | HTTP endpoints end to end | Yes | Real PostgreSQL |

The same **contract suite** (`tests/Contracts`) runs against the in-memory and
the PostgreSQL repositories, so the fakes used in unit tests can't drift from
production behaviour ([ADR 0003](docs/adr/0003-repositories-with-contract-tests.md)).

```bash
composer test:unit         # fast feedback, no framework
composer test:integration  # against PostgreSQL, including the concurrency test
composer ci                # pint + rector + phpstan + all tests
```

## Running locally

```bash
cp .env.example .env
docker compose up -d       # app on http://localhost:8000, PostgreSQL on :5432
docker compose exec app php artisan key:generate
docker compose exec app composer test
```

Without Docker, you need PHP 8.3 with `pdo_pgsql` and PostgreSQL 16 with the `btree_gist` extension available.

## Architecture Decision Records

- [0001: Layered architecture with a framework-free domain](docs/adr/0001-layered-architecture-with-framework-free-domain.md)
- [0002: PostgreSQL exclusion constraint to prevent overbooking](docs/adr/0002-exclusion-constraint-for-overbooking.md)
- [0003: Repositories over direct Eloquent, verified by contract tests](docs/adr/0003-repositories-with-contract-tests.md)
- [0004: Pricing rules and cancellation policies as strategies](docs/adr/0004-pricing-and-cancellation-as-strategies.md)
- [0005: Booking rules as composable specifications](docs/adr/0005-booking-rules-as-specifications.md)
- [0006: Payment gateway as a port with adapters and a shared contract](docs/adr/0006-payment-gateway-port-and-adapters.md)
- [0007: Domain events delivered through a transactional outbox](docs/adr/0007-domain-events-and-transactional-outbox.md)

## Roadmap

- [x] Project foundation: layers, tooling, CI, Docker
- [x] `Money` and `TimeSlot` value objects
- [x] Overbooking prevented by an exclusion constraint, with integration tests
- [x] `Space` and `Booking` aggregates, `BookSpace` use case, repositories with contract tests
- [x] Concurrency test proving one winner under parallel load
- [x] Pricing rules (Strategy) composed into a pricing pipeline, with itemised quotes
- [x] Cancellation policies and refunds
- [ ] Recurring bookings (weekly series) with a recurrence discount
- [x] Booking validation via composable Specifications, opening hours per space
- [x] Payment gateway port with Fake and Stripe adapters, plus contract tests
- [ ] Expire unpaid bookings after a few minutes
- [x] Domain events → e-mail, webhooks, audit (transactional outbox)
- [ ] REST API with OpenAPI docs and feature tests
- [ ] React + TypeScript dashboard, Playwright E2E
- [ ] NestJS real-time notification service (WebSocket)
- [ ] Mutation testing with Infection
