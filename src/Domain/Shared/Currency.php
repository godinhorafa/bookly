<?php

declare(strict_types=1);

namespace Domain\Shared;

enum Currency: string
{
    case BRL = 'BRL';
    case USD = 'USD';
    case EUR = 'EUR';

    public function minorUnits(): int
    {
        return 2;
    }
}
