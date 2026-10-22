<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Domain\Booking\Exception\BookingAlreadyStarted;
use Domain\Booking\Exception\BookingNotFound;
use Domain\Booking\Exception\BookingRulesViolated;
use Domain\Booking\Exception\InvalidBookingTransition;
use Domain\Booking\Exception\SlotUnavailable;
use Domain\Payment\Exception\BookingNotPayable;
use Domain\Payment\Exception\PaymentGatewayUnavailable;
use Domain\Payment\Exception\RefundRejected;
use Domain\Shared\Exception\DomainException;
use Domain\Space\Exception\SpaceNotFound;
use Illuminate\Validation\ValidationException;

/**
 * The single place that decides how domain failures look over HTTP.
 * Controllers never catch domain exceptions; they bubble up to here.
 */
final class DomainProblems
{
    public static function from(DomainException $e): Problem
    {
        return match (true) {
            $e instanceof SpaceNotFound, $e instanceof BookingNotFound => new Problem(404, 'not-found', 'Resource not found', $e->getMessage()),

            $e instanceof SlotUnavailable => new Problem(409, 'slot-unavailable', 'Slot unavailable', $e->getMessage()),

            $e instanceof BookingRulesViolated => new Problem(422, 'booking-rules-violated', 'Booking rules violated', 'The booking request breaks one or more rules.', [
                'violations' => $e->violations,
            ]),

            $e instanceof BookingNotPayable,
            $e instanceof BookingAlreadyStarted,
            $e instanceof InvalidBookingTransition => new Problem(409, 'invalid-state', 'Not allowed in the current state', $e->getMessage()),

            $e instanceof PaymentGatewayUnavailable => new Problem(503, 'payment-unavailable', 'Payment provider unavailable', 'The payment provider could not be reached. Retry with the same Idempotency-Key.', headers: ['Retry-After' => '30']),

            $e instanceof RefundRejected => new Problem(502, 'refund-rejected', 'Refund rejected by the payment provider', $e->getMessage()),

            // Invalid input caught by a value object: bad dates, coupon codes, ids...
            default => new Problem(422, 'invalid-request', 'Invalid request', $e->getMessage()),
        };
    }

    public static function fromValidation(ValidationException $e): Problem
    {
        return new Problem(422, 'validation-failed', 'Validation failed', $e->getMessage(), ['errors' => $e->errors()]);
    }
}
