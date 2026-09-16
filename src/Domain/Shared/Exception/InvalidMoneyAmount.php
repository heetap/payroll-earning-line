<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidMoneyAmount extends InvalidArgumentException implements DomainException
{
    public static function overflow(): self
    {
        return new self('The resulting amount does not fit in the supported range.');
    }
}
