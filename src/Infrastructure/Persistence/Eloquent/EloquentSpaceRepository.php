<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent;

use Domain\Cancellation\CancellationPolicyType;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Space\Exception\SpaceNotFound;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Infrastructure\Persistence\Eloquent\Models\SpaceModel;

final class EloquentSpaceRepository implements SpaceRepository
{
    public function get(SpaceId $id): Space
    {
        $model = SpaceModel::query()->find($id->value);

        if (! $model instanceof SpaceModel) {
            throw SpaceNotFound::withId($id);
        }

        return new Space(
            SpaceId::fromString($model->id),
            $model->name,
            $model->timezone,
            $model->capacity,
            Money::of($model->hourly_rate_amount, Currency::from($model->currency)),
            CancellationPolicyType::from($model->cancellation_policy),
        );
    }

    public function save(Space $space): void
    {
        SpaceModel::query()->updateOrCreate(['id' => $space->id->value], [
            'name' => $space->name,
            'timezone' => $space->timezone->getName(),
            'capacity' => $space->capacity,
            'hourly_rate_amount' => $space->hourlyRate->amount,
            'currency' => $space->hourlyRate->currency->value,
            'cancellation_policy' => $space->cancellationPolicy->value,
        ]);
    }
}
