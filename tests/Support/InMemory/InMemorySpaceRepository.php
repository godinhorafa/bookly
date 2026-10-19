<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use Domain\Space\Exception\SpaceNotFound;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;

final class InMemorySpaceRepository implements SpaceRepository
{
    /** @var array<string, Space> */
    private array $spaces = [];

    public function get(SpaceId $id): Space
    {
        return $this->spaces[$id->value] ?? throw SpaceNotFound::withId($id);
    }

    public function save(Space $space): void
    {
        $this->spaces[$space->id->value] = $space;
    }
}
