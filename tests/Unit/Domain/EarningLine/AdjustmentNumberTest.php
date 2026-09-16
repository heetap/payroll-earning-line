<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdjustmentNumberTest extends TestCase
{
    public function test_adjustments_are_numbered_from_one_upwards(): void
    {
        self::assertSame(2, new AdjustmentNumber(1)->next()->value);
        self::assertSame(3, new AdjustmentNumber(1)->next()->next()->value);
    }

    public function test_next_leaves_the_original_untouched(): void
    {
        $first = new AdjustmentNumber(1);
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
