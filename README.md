# Bookly

> A space booking API (coworking desks, meeting rooms, sports courts) built to
> show how to design a Laravel application around a framework-free domain: real
> business rules, design patterns where they make sense, and tests at every layer.

![CI](https://github.com/godinhorafa/bookly/actions/workflows/ci.yml/badge.svg)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4)
![PHPStan max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)
![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16-336791)
![NestJS 12](https://img.shields.io/badge/NestJS-12-E0234E)
![Mutation score 100%](https://img.shields.io/badge/mutation%20score-100%25-brightgreen)

## Why this project

Booking is a small domain with non-trivial rules:

- **No overbooking**, even under concurrent requests
- **Dynamic pricing**: peak hours, recurring-booking discounts, coupons
- **Configurable cancellation policies**: 100% refund up to 48h before, 50% up to 24h, and so on
- **Multiple payment gateways** and **notifications** (e-mail, webhooks)
- **Live calendars**: when someone books, every open dashboard updates

## Architecture

```mermaid
flowchart LR
    HTTP["App\\ (HTTP)<br/>controllers, requests, resources"] --> APP
    INFRA["Infrastructure\\<br/>Eloquent repositories, gateways, PostgreSQL"] --> APP
    APP["Application\\<br/>use cases, DTOs, ports"] --> DOMAIN
    INFRA --> DOMAIN
    DOMAIN["Domain\\<br/>entities, value objects, policies<br/><i>plain PHP, no framework</i>"]
```

Around the API, booking events flow out through the outbox to a separate
real-time service:

```mermaid
flowchart LR
    API["Laravel API"] -- "same transaction" --> OUTBOX[("outbox")]
    OUTBOX --> RELAY["outbox:relay --watch"]
    RELAY -- "signed webhook" --> RT["NestJS realtime service"]
    RELAY --> OTHER["audit, e-mail, partner webhooks"]
    RT -- "Socket.IO" --> DASH["React dashboards"]
    DASH -- "REST (refetch)" --> API
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
| Real time | A **NestJS** service receives the same signed webhooks as any partner, dedupes redeliveries, and pushes over Socket.IO: *slot taken/freed* to everyone (no personal data), *your booking changed* to its owner. Dashboards refetch on these hints ([ADR 0008](docs/adr/0008-realtime-service-fed-by-signed-webhooks.md)) |
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
| `Feature` | HTTP contract of every endpoint, including problem responses and idempotency | Yes | Real PostgreSQL |

The same **contract suite** (`tests/Contracts`) runs against the in-memory and
the PostgreSQL repositories, so the fakes used in unit tests can't drift from
production behaviour ([ADR 0003](docs/adr/0003-repositories-with-contract-tests.md)).

**Mutation testing** checks that the tests would actually catch a bug. Pest
changes the domain and use-case code one small way at a time (`>` into `>=`,
`+ 1` into `+ 2`, a dropped call) and every change must make a unit test fail.
CI requires **100% of mutants killed**, plus **95% line coverage** overall. The
first run scored 82%, and the survivors were real gaps: untested event
payloads, boundaries and a coupon expiry check
([ADR 0009](docs/adr/0009-mutation-testing.md)).

The realtime service has its own suite (Vitest, `realtime/`): unit tests for
the signature, parsing and fan-out rules, and end-to-end tests that drive the
real Nest app over HTTP and Socket.IO. The webhook signature is checked by
**both** languages against one shared example
([`docs/api/webhook-signature.example.json`](docs/api/webhook-signature.example.json)).

```bash
composer test:unit         # fast feedback, no framework
composer test:integration  # against PostgreSQL, including the concurrency test
composer ci                # pint + rector + phpstan + all tests
composer test:mutate       # mutation testing of src/Domain and src/Application (needs PCOV or Xdebug)
composer test:coverage     # coverage report, fails under 95%
```

## Running locally

```bash
cp .env.example .env
docker compose up -d       # API :8000, dashboard http://localhost:5173, realtime :3000, PostgreSQL :5432
docker compose exec app php artisan key:generate
docker compose exec app composer test
```

The database is seeded with demo data: log in as **demo@bookly.test / password**.

Without Docker, you need PHP 8.3 with `pdo_pgsql` and PostgreSQL 16 with the `btree_gist` extension available.

## HTTP API

Bearer-token API (Laravel Sanctum). The OpenAPI document is generated from the
code by [Scramble](https://scramble.dedoc.co) and committed as
[`docs/api/openapi.json`](docs/api/openapi.json) (CI fails if it is stale).
With the app running locally it is browsable at `/docs/api`.

| Method | Path | |
|---|---|---|
| `POST` | `/api/tokens` | Exchange credentials for a token |
| `GET` | `/api/spaces` | Spaces with rate, cancellation policy and opening hours |
| `GET` | `/api/spaces/{id}/availability?from=&to=` | Busy slots (no customer data) |
| `GET` | `/api/bookings` | My bookings |
| `POST` | `/api/bookings` | Book a space: itemised quote in the response |
| `GET` | `/api/bookings/{id}` | One of my bookings |
| `POST` | `/api/bookings/{id}/payment` | Pay and confirm (`402` with the reason if declined) |
| `POST` | `/api/bookings/{id}/cancellation` | Cancel; the refund follows the space's policy |

- **Errors** are RFC 9457 `application/problem+json`, mapped from domain
  exceptions in one place ([problem types](docs/api/problems.md)). Rule
  violations list *every* broken rule.
- **`Idempotency-Key`** on every `POST`: a retry returns the original response
  instead of booking or charging twice (`Idempotent-Replayed: true`).

```bash
curl -X POST localhost:8000/api/bookings \
  -H "Authorization: Bearer $TOKEN" -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"space_id":"…","start":"2026-11-03T18:00:00-03:00","end":"2026-11-03T20:00:00-03:00","coupon":"WELCOME10"}'
```

## Dashboard (React + TypeScript)

![Dashboard](docs/screenshots/dashboard.png)

A small dashboard in [`frontend/`](frontend): pick a space, see the day in the
**space's own time zone** (free, busy, closed), book, read the itemised quote,
pay with a test card and cancel. It is kept organised rather than polished:

- **No hand-written API types**: `src/api/schema.d.ts` is generated from
  `docs/api/openapi.json` with `openapi-typescript`, and the typed client
  (`openapi-fetch`) rejects wrong paths, params or bodies at compile time.
  CI fails if the generated types are stale.
- Every failed request becomes a `ProblemError`, so the UI shows *all*
  broken booking rules from the API's problem document.
- Each mutation sends a fresh `Idempotency-Key`.
- **Live updates**: a Socket.IO connection to the realtime service invalidates
  the affected queries, and a *Live activity* card lists recent changes.
  Without the service, the dashboard works as before.
- TanStack Query for server state; time-zone maths in one module (`lib/time.ts`), unit tested across DST changes.

| Layer | Tool | What |
|---|---|---|
| Unit / component | Vitest + Testing Library | Time and money helpers, schedule model, booking form against a mocked API |
| End to end | Playwright | The whole customer journey against the **real** Laravel API and PostgreSQL with seeded data: log in, book, declined card, pay, invalid request, cancel with refund. Then **two browsers**: one books and cancels, and the other's calendar follows without a reload (API → outbox → relay → webhook → NestJS → WebSocket) |

```bash
cd frontend
npm ci
npm run dev        # http://localhost:5173, proxies /api to the Laravel app on :8000
npm test           # unit + component tests
npm run e2e        # starts the API (fresh `bookly_e2e` database), relay and realtime service, then runs Playwright
```

## Realtime service (NestJS)

[`realtime/`](realtime) is a small NestJS 12 app, organised as ports and adapters like the API:

| Piece | Role |
|---|---|
| `WebhooksController` + `BooklySignatureGuard` | `POST /webhooks/bookly`: HMAC over the raw body, 5-minute replay window |
| `parseBookingEvent` | Validates the body; unknown event types are accepted and ignored |
| `BookingEvents` + `RecentEvents` | Dedupes at-least-once redeliveries, then fans out |
| `fanOut` | Pure function: who hears what (the privacy rules live and are tested here) |
| `RealtimeGateway` | Socket.IO; authenticates the handshake through the `IdentityProvider` port (`GET /api/me` on the API) |

```bash
cd realtime
npm ci
npm test           # unit + end-to-end (real HTTP and WebSocket)
WEBHOOK_SECRET=… API_URL=http://127.0.0.1:8000 npm run dev
```

The webhook format and how to verify it are in [docs/api/webhooks.md](docs/api/webhooks.md).

## Anti-patterns: a fat controller, refactored

The booking endpoint was first committed as a deliberate **fat controller**
(validation, rules, pricing and SQL in one 89-line method, with real bugs:
peak hours in UTC, no opening hours, an unhandled race). The next commit turns it into a 6-line
HTTP adapter over the use case, with the **same feature tests passing
unchanged**. See [docs/refactoring/fat-controller.md](docs/refactoring/fat-controller.md).

## Architecture Decision Records

- [0001: Layered architecture with a framework-free domain](docs/adr/0001-layered-architecture-with-framework-free-domain.md)
- [0002: PostgreSQL exclusion constraint to prevent overbooking](docs/adr/0002-exclusion-constraint-for-overbooking.md)
- [0003: Repositories over direct Eloquent, verified by contract tests](docs/adr/0003-repositories-with-contract-tests.md)
- [0004: Pricing rules and cancellation policies as strategies](docs/adr/0004-pricing-and-cancellation-as-strategies.md)
- [0005: Booking rules as composable specifications](docs/adr/0005-booking-rules-as-specifications.md)
- [0006: Payment gateway as a port with adapters and a shared contract](docs/adr/0006-payment-gateway-port-and-adapters.md)
- [0007: Domain events delivered through a transactional outbox](docs/adr/0007-domain-events-and-transactional-outbox.md)
- [0008: A NestJS real-time service fed by signed webhooks](docs/adr/0008-realtime-service-fed-by-signed-webhooks.md)
- [0009: Mutation testing gates the domain and use cases](docs/adr/0009-mutation-testing.md)

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
- [x] REST API with OpenAPI docs, idempotency keys and feature tests
- [x] A fat controller refactored into a thin adapter, with the same tests
- [x] React + TypeScript dashboard with types generated from OpenAPI, Playwright E2E
- [x] NestJS real-time notification service (WebSocket)
- [x] Mutation testing (Pest) at 100%, coverage gate at 95%
