<?php

declare(strict_types=1);

/*
 * Endpoints that receive booking.* events, signed with the shared secret.
 * WEBHOOK_ENDPOINTS is a comma-separated list of URLs.
 */
return [
    'endpoints' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBHOOK_ENDPOINTS', ''))))),
    'secret' => env('WEBHOOK_SECRET', ''),
];
