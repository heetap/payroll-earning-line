<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidEarningLineId extends InvalidArgumentException implements DomainException
{
    public static function notAUuid(string $value): self
    {
        return new self(sprintf('An earning line id must be a UUID, got "%s".', $value));
    }
}
