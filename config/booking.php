<?php

declare(strict_types=1);

/*
 * Rules every booking request must satisfy. Each entry maps to one
 * specification in Domain\Booking\Specification; see ADR 0005.
 */
return [
    'minimum_notice_minutes' => (int) env('BOOKING_MINIMUM_NOTICE_MINUTES', 60),
    'horizon_days' => (int) env('BOOKING_HORIZON_DAYS', 180),
    'minimum_duration_minutes' => (int) env('BOOKING_MINIMUM_DURATION_MINUTES', 30),
    'maximum_duration_minutes' => (int) env('BOOKING_MAXIMUM_DURATION_MINUTES', 12 * 60),
    'grid_minutes' => (int) env('BOOKING_GRID_MINUTES', 15),
    'max_upcoming_per_customer' => (int) env('BOOKING_MAX_UPCOMING_PER_CUSTOMER', 5),

    // How long an unpaid booking holds its slot before it expires (ADR 0010).
    'payment_hold_minutes' => (int) env('BOOKING_PAYMENT_HOLD_MINUTES', 15),
];
