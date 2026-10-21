<?php

declare(strict_types=1);

/*
 * The gateway new charges go to. "fake" needs no credentials and accepts
 * Stripe's test payment methods (pm_card_visa, pm_card_chargeDeclined...).
 * Refunds always go back to the gateway that took the charge.
 */
return [
    'default' => env('PAYMENT_GATEWAY', 'fake'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
    ],
];
