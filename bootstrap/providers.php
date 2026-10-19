<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Infrastructure\Laravel\InfrastructureServiceProvider;

return [
    AppServiceProvider::class,
    InfrastructureServiceProvider::class,
];
