<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\Shared\DomainException;

final class UnknownAdjustment extends \DomainException implements DomainException
{
    public static function number(AdjustmentNumber $number, EarningLineId $id): self
    {
        return new self(sprintf(
            'Line %s has no adjustment #%d to compensate.',
            $id->value,
            $number->value,
        ));
    }
}
