# Refactoring a fat controller

The job description mentions anti-patterns explicitly, so this repository
contains one on purpose, plus its refactoring, as two consecutive commits
on the same feature tests:

| Commit | What |
|---|---|
| `Add booking endpoint as a fat controller (deliberate anti-pattern)` | The "before": `POST /api/bookings` written the way it often is in a typical codebase |
| `Refactor the booking endpoint into a thin controller over the use case` | The "after": the same endpoint as an HTTP adapter over `BookSpaceHandler` |

Browse them with `git log --follow app/Http/Controllers/Api/BookingController.php`.

## Before

One 89-line `store()` method does everything ([full file](https://github.com/godinhorafa/bookly/blob/faa34de/app/Http/Controllers/Api/BookingController.php)):

```php
public function store(Request $request): JsonResponse
{
    $data = $request->validate([...]);
    $space = DB::table('spaces')->where('id', $data['space_id'])->first();
    // business rules
    if ($start->lt(now()->addMinutes(60))) {
        return response()->json(['message' => '...'], 422);
    }
    // ... duration, grid, per-customer limit
    // check availability
    $overlap = DB::table('bookings')->...->exists();
    if ($overlap) {
        return response()->json(['message' => 'This slot is already booked.'], 409);
    }
    // price
    $price = (int) round($space->hourly_rate_amount * $minutes / 60);
    if ($start->isWeekday() && $hour >= 18 && $hour < 22) { $price = (int) round($price * 1.25); }
    // ... long booking, coupon
    DB::table('bookings')->insert([...]);
    return response()->json(['data' => [...]], 201);
}
```

### What is wrong with it

| Problem | Consequence |
|---|---|
| **Single Responsibility** violated: HTTP, validation, business rules, pricing, persistence and formatting in one method | Every change touches the same method, and nothing can be reused (a CLI or queue entry point would copy it) |
| **Business rules duplicated** from the domain, and already drifting | Peak hours computed on the **UTC** hour instead of the space's local time; opening hours ignored; floats (`* 1.25`) in money math |
| **Check-then-insert race** with no handling of the exclusion constraint | Two simultaneous requests: one gets a booking, the other an unhandled `QueryException`, i.e. a **500** instead of a 409 |
| **Reads the system clock** (`now()`) and the database directly | Can only be tested end to end, with `Carbon::setTestNow` |
| **Stops at the first broken rule**, with ad-hoc `{ "message": … }` bodies | Customers fix problems one at a time; clients must parse a different error shape per endpoint |
| **Untyped** `stdClass` rows and mixed arithmetic | 13 PHPStan (level max) errors; the commit had to exclude the file from analysis |
| **No events** | Nothing is audited, e-mailed or sent to webhooks |

## After

```php
final readonly class BookingController
{
    public function __construct(private BookSpaceHandler $bookSpace) {}

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $result = $this->bookSpace->handle($request->toCommand());

        return new JsonResponse(['data' => BookingPresenter::booking($result->booking, $result->quote)], 201);
    }
}
```

Each responsibility now has one home:

| Concern | Where |
|---|---|
| Request shape | `CreateBookingRequest` (FormRequest) |
| Business rules | `BookingRules` specifications (ADR 0005) |
| Pricing | `PricingPipeline` strategies (ADR 0004) |
| Overbooking, including races | Exclusion constraint → `SlotUnavailable` → **409** (ADR 0002) |
| Persistence and events | Repositories + transactional outbox (ADR 0003, 0007) |
| Error format | `DomainProblems` → RFC 9457 `application/problem+json` |
| JSON shape | `BookingPresenter` |

## How the refactoring was kept safe

1. **Contract first.** `tests/Feature/Api/CreateBookingTest.php` describes the
   endpoint only through HTTP: status codes, response fields and database
   effects. It was written against the fat controller and passes against both
   versions. **The refactoring commit does not modify it.**
2. **Characterise the bugs.** `tests/Feature/Api/BookingDomainRulesOverHttpTest.php`
   pins down behaviour the fat controller got wrong. All 4 tests failed
   against the "before":

   ```
   - charges peak hours in the space local time, not in UTC   → 8000 instead of 10000
   - respects the opening hours of the space                  → 201 instead of 422
   - reports every broken rule at once, as a problem document → application/json, one message
   - explains the price with an itemised quote                → no quote
   ```

   All 4 pass after the refactoring.

| | Before | After |
|---|---|---|
| `store()` length | 89 lines | 6 lines |
| PHPStan level max | 13 errors (file excluded) | 0 errors |
| Contract tests (`CreateBookingTest`) | 7 / 7 | 7 / 7, unchanged |
| Domain behaviour tests | 0 / 4 | 4 / 4 |
