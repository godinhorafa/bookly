<?php

declare(strict_types=1);

namespace Domain\Space\Exception;

use Domain\Shared\Exception\DomainException;
use Domain\Space\SpaceId;

final class SpaceNotFound extends DomainException
{
    public static function withId(SpaceId $id): self
    {
        return new self("Space {$id} was not found.");
    }
}
