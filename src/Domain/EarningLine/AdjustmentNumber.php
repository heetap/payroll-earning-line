<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentNumber;

/**
 * Sequential per line, so it matches the "adjustment #4" language the
 * business already uses when one correction compensates another.
 */
final readonly class AdjustmentNumber
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw InvalidAdjustmentNumber::notPositive($value);
        }
    }

    public static function first(): self
    {
        return new self(1);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
