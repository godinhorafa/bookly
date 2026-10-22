<?php

declare(strict_types=1);

use App\Http\Problem\DomainProblems;
use App\Http\Problem\Problem;
use Domain\Shared\Exception\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (DomainException $e, Request $request): ?JsonResponse => $request->is('api/*')
            ? DomainProblems::from($e)->toResponse()
            : null);

        $exceptions->render(fn (ValidationException $e, Request $request): ?JsonResponse => $request->is('api/*')
            ? DomainProblems::fromValidation($e)->toResponse()
            : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request): ?JsonResponse => $request->is('api/*')
            ? (new Problem(401, 'unauthenticated', 'Unauthenticated', 'A valid bearer token is required.'))->toResponse()
            : null);
    })->create();
