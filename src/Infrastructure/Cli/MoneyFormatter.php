<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Cli;

use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;

/**
 * Presentation, deliberately outside the domain: currency symbols and digit
 * grouping are things a reader wants, not things a rule depends on.
 */
final class MoneyFormatter
{
    public function format(Money $money): string
    {
        $scale = $money->currency->minorUnits();

        // The sign is stripped as text because abs(PHP_INT_MIN) is a float,
        // and floats are exactly what this codebase refuses to touch.
        $digits = str_pad(ltrim((string) $money->minor, '-'), $scale + 1, '0', \STR_PAD_LEFT);

        $units = $scale > 0 ? substr($digits, 0, -$scale) : $digits;
        $fraction = $scale > 0 ? '.' . substr($digits, -$scale) : '';

        $grouped = $units
            |> strrev(...)
            |> (static fn(string $reversed): array => str_split($reversed, 3))
            |> (static fn(array $groups): string => strrev(implode(',', $groups)));

        return ($money->minor < 0 ? '-' : '') . $this->symbol($money->currency) . $grouped . $fraction;
    }

    private function symbol(Currency $currency): string
    {
        return match ($currency) {
            Currency::USD => '$',
            Currency::EUR => '€',
            Currency::GBP => '£',
        };
    }
}
