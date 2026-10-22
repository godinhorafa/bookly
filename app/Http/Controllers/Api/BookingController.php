<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Presenters\BookingPresenter;
use App\Http\Requests\CreateBookingRequest;
use Application\Booking\BookSpaceHandler;
use Illuminate\Http\JsonResponse;

/**
 * HTTP adapter for booking use cases: translate the request into a command,
 * run it, present the result. Business rules live in the domain; failures
 * bubble up as domain exceptions and become problem responses (DomainProblems).
 */
final readonly class BookingController
{
    public function __construct(private BookSpaceHandler $bookSpace) {}

    /**
     * Book a space.
     *
     * The slot is held as `pending` until it is paid. The response includes an
     * itemised quote explaining the price.
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $result = $this->bookSpace->handle($request->toCommand());

        return new JsonResponse(['data' => BookingPresenter::booking($result->booking, $result->quote)], 201);
    }
}
