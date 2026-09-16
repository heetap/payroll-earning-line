<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared\Exception;

use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class CurrencyMismatch extends InvalidArgumentException implements DomainException
{
    public static function between(Currency $expected, Currency $actual): self
    {
        return new self(sprintf(
            'Expected %s, got %s. A line carries one currency for its lifetime.',
            $expected->value,
            $actual->value,
        ));
    }
}
