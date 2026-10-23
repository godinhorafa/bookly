<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Presenters\BookingPresenter;
use App\Http\Problem\Problem;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\PayBookingRequest;
use App\Models\User;
use Application\Booking\BookingQueries;
use Application\Booking\BookSpaceHandler;
use Application\Booking\CancelBooking;
use Application\Booking\CancelBookingHandler;
use Application\Payment\PayForBooking;
use Application\Payment\PayForBookingHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter for booking use cases: translate the request into a command,
 * run it, present the result. Business rules live in the domain; failures
 * bubble up as domain exceptions and become problem responses (DomainProblems).
 */
final readonly class BookingController
{
    public function __construct(
        private BookSpaceHandler $bookSpace,
        private PayForBookingHandler $pay,
        private CancelBookingHandler $cancel,
        private BookingQueries $bookings,
    ) {}

    /**
     * List my bookings, latest first.
     */
    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => array_map(
            BookingPresenter::booking(...),
            $this->bookings->ofCustomer($this->customerId($request)),
        )]);
    }

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

    /**
     * Show one of my bookings.
     */
    public function show(Request $request, string $booking): JsonResponse
    {
        return new JsonResponse(['data' => BookingPresenter::booking($this->bookings->get($booking, $this->customerId($request)))]);
    }

    /**
     * Pay for a pending booking, which confirms it.
     *
     * A declined card is answered with `402` and the provider's reason; the
     * booking stays pending so the customer can try another card.
     */
    public function pay(PayBookingRequest $request, string $booking): JsonResponse
    {
        $customerId = $this->customerId($request);
        $outcome = $this->pay->handle(new PayForBooking($booking, $customerId, $request->string('payment_method')->value()));

        if (! $outcome->confirmed) {
            return (new Problem(402, 'payment-declined', 'Payment declined', 'The payment method was declined. Try another one.', [
                'decline_reason' => $outcome->declineReason,
            ]))->toResponse();
        }

        return new JsonResponse(['data' => BookingPresenter::booking($this->bookings->get($booking, $customerId))]);
    }

    /**
     * Cancel one of my bookings. The refund follows the space's cancellation policy.
     */
    public function cancel(Request $request, string $booking): JsonResponse
    {
        $customerId = $this->customerId($request);
        $this->cancel->handle(new CancelBooking($booking, $customerId));

        return new JsonResponse(['data' => BookingPresenter::booking($this->bookings->get($booking, $customerId))]);
    }

    private function customerId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return $user->id;
    }
}
