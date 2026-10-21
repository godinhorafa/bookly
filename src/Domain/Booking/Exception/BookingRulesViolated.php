<?php

declare(strict_types=1);

namespace Domain\Booking\Exception;

use Domain\Shared\Exception\DomainException;

final class BookingRulesViolated extends DomainException
{
    /**
     * @param  list<string>  $violations
     */
    private function __construct(public readonly array $violations)
    {
        parent::__construct(implode(' ', $violations));
    }

    /**
     * @param  non-empty-list<string>  $violations
     */
    public static function because(array $violations): self
    {
        return new self($violations);
    }
}
