<?php

declare(strict_types=1);

/*
 * Pricing rules are applied in this order: surcharges first, then discounts on
 * the running total. See docs/adr/0004-pricing-and-cancellation-as-strategies.md.
 */
return [
    'peak_hours' => [
        'surcharge_percent' => (int) env('PRICING_PEAK_SURCHARGE_PERCENT', 25),
        // [ISO weekdays (1 = Monday), start, end] in each space's local time
        'windows' => [
            [[1, 2, 3, 4, 5], '18:00', '22:00'],
            [[6, 7], '09:00', '13:00'],
        ],
    ],

    'long_booking' => [
        'minimum_minutes' => (int) env('PRICING_LONG_BOOKING_MINUTES', 240),
        'discount_percent' => (int) env('PRICING_LONG_BOOKING_PERCENT', 10),
    ],
];
