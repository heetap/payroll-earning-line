<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\Shared;

use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Exception\CurrencyMismatch;
use Alcor\Payroll\Domain\Shared\Exception\InvalidMoneyAmount;
use Alcor\Payroll\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validDecimals(): iterable
    {
        yield 'whole amount' => ['1000.00', 100_000];
        yield 'no decimal point' => ['1050', 105_000];
        yield 'one decimal' => ['1.5', 150];
        yield 'negative' => ['-45.55', -4_555];
        yield 'explicit plus' => ['+100.10', 10_010];
        yield 'negative below one' => ['-0.10', -10];
        yield 'zero' => ['0.00', 0];
        yield 'negative zero is zero' => ['-0.00', 0];
        yield 'leading zeros' => ['0001.05', 105];
    }

    #[DataProvider('validDecimals')]
    public function test_it_parses_a_decimal_string_into_minor_units(string $amount, int $expected): void
    {
        self::assertSame($expected, Money::fromDecimal($amount, Currency::USD)->minor);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDecimals(): iterable
    {
        yield 'too many decimals' => ['1.005'];
        yield 'not a number' => ['abc'];
        yield 'empty' => [''];
        yield 'leading space' => [' 1.00'];
        yield 'trailing point' => ['1.'];
        yield 'no integer digit' => ['.5'];
        yield 'comma separator' => ['1,00'];
        yield 'scientific notation' => ['1e2'];
        yield 'double sign' => ['--1.00'];
        yield 'sign only' => ['-'];
        yield 'out of int range' => ['99999999999999999999.00'];
        yield 'trailing newline' => ["1.00\n"];
        yield 'trailing carriage return' => ["1.00\r"];
        yield 'leading newline' => ["\n1.00"];
        yield 'trailing space' => ['1.00 '];
        yield 'inner newline' => ["1.\n00"];
    }

    #[DataProvider('invalidDecimals')]
    public function test_it_rejects_anything_that_is_not_an_exact_decimal(string $amount): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        Money::fromDecimal($amount, Currency::USD);
    }

    public function test_it_adds_amounts_of_the_same_currency(): void
    {
        $sum = Money::fromDecimal('1050.00', Currency::USD)
            ->add(Money::fromDecimal('-45.55', Currency::USD));

        self::assertSame(100_445, $sum->minor);
    }

    public function test_adding_leaves_the_original_untouched(): void
    {
        $original = Money::fromDecimal('10.00', Currency::USD);

        // (void) is how PHP 8.5 spells a deliberate discard of a #[\NoDiscard]
        // result; without it this raises a warning and PHPUnit fails the test.
        (void) $original->add(Money::fromDecimal('1.00', Currency::USD));

        self::assertSame(1_000, $original->minor);
    }

    public function test_adding_a_different_currency_is_refused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        // (void) is how PHP 8.5 spells a deliberate discard of a #[\NoDiscard]
        // result; the engine flags a bare statement call regardless of the
        // exception thrown inside it, so every such call here needs it.
        (void) Money::fromDecimal('1.00', Currency::USD)->add(Money::fromDecimal('1.00', Currency::EUR));
    }

    public function test_addition_that_would_leave_the_integer_range_is_refused(): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        (void) Money::ofMinor(\PHP_INT_MAX, Currency::USD)->add(Money::ofMinor(1, Currency::USD));
    }

    public function test_subtraction_that_would_leave_the_integer_range_is_refused(): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        (void) Money::ofMinor(\PHP_INT_MIN, Currency::USD)->add(Money::ofMinor(-1, Currency::USD));
    }

    public function test_zero_is_zero(): void
    {
        self::assertTrue(Money::zero(Currency::USD)->isZero());
        self::assertFalse(Money::ofMinor(1, Currency::USD)->isZero());
    }

    public function test_equality_covers_both_amount_and_currency(): void
    {
        $tenUsd = Money::fromDecimal('10.00', Currency::USD);

        self::assertTrue($tenUsd->equals(Money::fromDecimal('10.00', Currency::USD)));
        self::assertFalse($tenUsd->equals(Money::fromDecimal('10.01', Currency::USD)));
        self::assertFalse($tenUsd->equals(Money::fromDecimal('10.00', Currency::EUR)));
    }
}
