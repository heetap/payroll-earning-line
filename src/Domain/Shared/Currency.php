<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared;

use Alcor\Payroll\Domain\Shared\Exception\UnsupportedCurrency;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';

    /**
     * Precision is asked of the currency rather than assumed, so a 0- or
     * 3-decimal currency can be added without touching Money.
     */
    public function minorUnits(): int
    {
        return match ($this) {
            self::USD, self::EUR, self::GBP => 2,
        };
    }

    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw new UnsupportedCurrency(sprintf('Currency "%s" is not supported.', $code));
    }
}
