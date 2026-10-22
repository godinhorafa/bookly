# API problem types

Every error response follows [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457)
(`Content-Type: application/problem+json`):

```json
{
  "type": "https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#slot-unavailable",
  "title": "Slot unavailable",
  "status": 409,
  "detail": "Space 0b9e… is already booked between 2026-11-03T13:00:00+00:00 and 2026-11-03T14:00:00+00:00."
}
```

Domain exceptions are mapped in one place, `App\Http\Problem\DomainProblems`.

| Type | Status | When |
|---|---|---|
| <a id="unauthenticated"></a>`unauthenticated` | 401 | Missing or invalid bearer token |
| <a id="not-found"></a>`not-found` | 404 | Unknown space or booking, or a booking that belongs to someone else (indistinguishable on purpose) |
| <a id="payment-declined"></a>`payment-declined` | 402 | The card was declined; `decline_reason` carries the provider's code (e.g. `insufficient_funds`). The booking stays pending |
| <a id="slot-unavailable"></a>`slot-unavailable` | 409 | The slot overlaps an active booking, including when two requests race for it |
| <a id="invalid-state"></a>`invalid-state` | 409 | Paying a booking that isn't pending, cancelling one that started or was already cancelled |
| <a id="idempotency-conflict"></a>`idempotency-conflict` | 409 / 422 | An `Idempotency-Key` reused while the first request is in flight (409) or with a different body (422) |
| <a id="validation-failed"></a>`validation-failed` | 422 | Malformed request body; `errors` maps each field to its messages |
| <a id="booking-rules-violated"></a>`booking-rules-violated` | 422 | Booking rules (notice, duration, grid, opening hours…); `violations` lists *every* broken rule |
| <a id="invalid-request"></a>`invalid-request` | 422 | A value the domain rejects: unparseable date, unknown or expired coupon… |
| <a id="refund-rejected"></a>`refund-rejected` | 502 | The payment provider refused the refund |
| <a id="payment-unavailable"></a>`payment-unavailable` | 503 | The payment provider is unreachable; retry with the same `Idempotency-Key` (`Retry-After` is set) |
