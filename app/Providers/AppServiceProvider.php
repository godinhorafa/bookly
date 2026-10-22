<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\HttpSecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // OpenAPI is generated from the code (routes, FormRequests, return types);
        // docs/api/openapi.json is exported from it and checked in CI.
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->info->description = 'Space booking API. Errors use RFC 9457 problem details (docs/api/problems.md). '
                . 'Unsafe requests accept an Idempotency-Key header.';
            $openApi->secure((new HttpSecurityScheme('bearer'))->as('bearerAuth')->setDescription('Token from POST /api/tokens'));
        });
    }
}
