<?php

declare(strict_types=1);

namespace Domain\Space;

use Domain\Space\Exception\SpaceNotFound;

interface SpaceRepository
{
    /**
     * @throws SpaceNotFound
     */
    public function get(SpaceId $id): Space;

    public function save(Space $space): void;
}
