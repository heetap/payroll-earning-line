<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

use Alcor\Payroll\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class IgnoredRecalculation
{
    public function __construct(
        public Money $attemptedValue,
        public DateTimeImmutable $at,
    ) {}
}
