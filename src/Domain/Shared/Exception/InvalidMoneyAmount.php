<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared\Exception;

use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidMoneyAmount extends InvalidArgumentException implements DomainException
{
    public static function notDecimal(string $amount, Currency $currency): self
    {
        return new self(sprintf(
            'Expected a decimal amount with at most %d decimal places, got "%s".',
            $currency->minorUnits(),
            $amount,
        ));
    }

    public static function outOfRange(string $amount): self
    {
        return new self(sprintf('Amount "%s" does not fit in the supported range.', $amount));
    }

    public static function overflow(): self
    {
        return new self('The resulting amount does not fit in the supported range.');
    }
}
