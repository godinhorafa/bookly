<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Problem\Problem;
use App\Http\Requests\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class TokenController
{
    /**
     * Exchange credentials for a bearer token.
     *
     * @unauthenticated
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->value())->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->value(), $user->password)) {
            return (new Problem(401, 'unauthenticated', 'Invalid credentials', 'The e-mail or password is incorrect.'))->toResponse();
        }

        return new JsonResponse([
            'token' => $user->createToken($request->string('device_name')->value())->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * The authenticated customer.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
