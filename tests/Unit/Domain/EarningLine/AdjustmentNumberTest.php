<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdjustmentNumberTest extends TestCase
{
    public function test_the_first_adjustment_on_a_line_is_number_one(): void
    {
        self::assertSame(1, AdjustmentNumber::first()->value);
    }

    public function test_numbers_run_in_sequence(): void
    {
        self::assertSame(2, AdjustmentNumber::first()->next()->value);
        self::assertSame(3, AdjustmentNumber::first()->next()->next()->value);
    }

    public function test_next_leaves_the_original_untouched(): void
    {
        $first = AdjustmentNumber::first();
        $first->next();

        self::assertSame(1, $first->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonPositiveNumbers(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('nonPositiveNumbers')]
    public function test_adjustments_are_numbered_from_one(int $value): void
    {
        $this->expectException(InvalidAdjustmentNumber::class);

        new AdjustmentNumber($value);
    }

    public function test_equality_compares_the_number(): void
    {
        self::assertTrue(new AdjustmentNumber(4)->equals(new AdjustmentNumber(4)));
        self::assertFalse(new AdjustmentNumber(4)->equals(new AdjustmentNumber(5)));
    }
}
