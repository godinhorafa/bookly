<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

/**
 * Base type for every rule violation raised by the domain layer.
 * The HTTP layer maps it to a 422 without knowing the concrete exception.
 */
abstract class DomainException extends \DomainException {}
