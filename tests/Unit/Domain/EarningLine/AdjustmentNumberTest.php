<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdjustmentNumberTest extends TestCase
{
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
