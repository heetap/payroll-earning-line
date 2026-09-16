<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Infrastructure\Cli;

use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Alcor\Payroll\Infrastructure\Cli\MoneyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Currency, string}>
     */
    public static function amounts(): iterable
    {
        yield 'thousands are grouped' => ['1000.00', Currency::USD, '$1,000.00'];
        yield 'scenario value' => ['1104.45', Currency::USD, '$1,104.45'];
        yield 'below one thousand' => ['45.55', Currency::USD, '$45.55'];
        yield 'negative sign precedes the symbol' => ['-45.55', Currency::USD, '-$45.55'];
        yield 'below one' => ['0.10', Currency::USD, '$0.10'];
        yield 'zero' => ['0.00', Currency::USD, '$0.00'];
        yield 'millions' => ['1234567.89', Currency::USD, '$1,234,567.89'];
        yield 'euro' => ['1000.00', Currency::EUR, '€1,000.00'];
        yield 'pound' => ['1000.00', Currency::GBP, '£1,000.00'];
    }

    #[DataProvider('amounts')]
    public function test_it_renders_an_amount_for_a_human(string $amount, Currency $currency, string $expected): void
    {
        self::assertSame($expected, new MoneyFormatter()->format(Money::fromDecimal($amount, $currency)));
    }

    public function test_it_renders_the_smallest_representable_amount_without_touching_a_float(): void
    {
        self::assertStringStartsWith('-$', new MoneyFormatter()->format(Money::ofMinor(\PHP_INT_MIN, Currency::USD)));
    }
}
