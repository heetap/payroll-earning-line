<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\Shared\DomainException;

final class ZeroAdjustmentNotAllowed extends \DomainException implements DomainException
{
    public static function forLine(EarningLineId $id): self
    {
        return new self(sprintf(
            'An adjustment of zero would freeze line %s without correcting anything.',
            $id->value,
        ));
    }
}
