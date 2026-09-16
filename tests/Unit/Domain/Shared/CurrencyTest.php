<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\Shared;

use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Exception\UnsupportedCurrency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function test_supported_currencies_use_two_minor_units(): void
    {
        foreach (Currency::cases() as $currency) {
            self::assertSame(2, $currency->minorUnits());
        }
    }

    public function test_it_is_built_from_an_iso_4217_code(): void
    {
        self::assertSame(Currency::USD, Currency::fromCode('USD'));
    }

    public function test_an_unsupported_code_is_rejected_as_a_domain_error(): void
    {
        $this->expectException(UnsupportedCurrency::class);

        Currency::fromCode('XYZ');
    }
}
