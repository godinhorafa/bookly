<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Presenters\SpacePresenter;
use App\Http\Requests\AvailabilityRequest;
use Application\Space\SpaceQueries;
use Illuminate\Http\JsonResponse;

final readonly class SpaceController
{
    public function __construct(private SpaceQueries $spaces) {}

    /**
     * List bookable spaces.
     */
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => array_map(SpacePresenter::space(...), $this->spaces->all())]);
    }

    /**
     * Busy slots of a space between two instants (at most 62 days apart).
     */
    public function availability(AvailabilityRequest $request, string $space): JsonResponse
    {
        $availability = $this->spaces->availability(
            $space,
            $request->string('from')->value(),
            $request->string('to')->value(),
        );

        return new JsonResponse(['data' => SpacePresenter::availability($availability)]);
    }
}
