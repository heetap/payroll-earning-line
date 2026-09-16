<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidAdjustmentNumber extends InvalidArgumentException implements DomainException
{
    public static function notPositive(int $value): self
    {
        return new self(sprintf('Adjustments are numbered from 1, got %d.', $value));
    }
}
