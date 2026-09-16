<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidSpecialistId extends InvalidArgumentException implements DomainException
{
    public static function blank(): self
    {
        return new self('An adjustment must record who made it.');
    }

    public static function tooLong(int $length, int $limit): self
    {
        return new self(sprintf('A specialist id may be at most %d characters, got %d.', $limit, $length));
    }
}
