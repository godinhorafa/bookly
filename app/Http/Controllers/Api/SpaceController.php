<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\AvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\SpaceResource;
use Application\Space\SpaceQueries;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class SpaceController
{
    public function __construct(private SpaceQueries $spaces) {}

    /**
     * List bookable spaces.
     */
    public function index(): AnonymousResourceCollection
    {
        return SpaceResource::collection($this->spaces->all());
    }

    /**
     * Busy slots of a space between two instants (at most 62 days apart).
     */
    public function availability(AvailabilityRequest $request, string $space): AvailabilityResource
    {
        $availability = $this->spaces->availability(
            $space,
            $request->string('from')->value(),
            $request->string('to')->value(),
        );

        return new AvailabilityResource($availability);
    }
}
