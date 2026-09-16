<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class UnsupportedCurrency extends InvalidArgumentException implements DomainException
{
    public static function code(string $code): self
    {
        return new self(sprintf('Currency "%s" is not supported.', $code));
    }
}
