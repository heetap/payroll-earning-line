<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\Shared\DomainException;
use RuntimeException;

/**
 * Keeps its factory: raised from both the repository and the audit query handler, so
 * inlining would duplicate the wording across two layers.
 */
final class EarningLineNotFound extends RuntimeException implements DomainException
{
    public static function withId(EarningLineId $id): self
    {
        return new self(sprintf('Earning line %s has not been calculated.', $id->value));
    }
}
