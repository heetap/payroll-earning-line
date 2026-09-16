<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Event;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Alcor\Payroll\Domain\Shared\Money;
use DateTimeImmutable;

/**
 * Recorded so the drift between what the system would have calculated and
 * what the line is actually worth stays visible. It changes nothing.
 */
final readonly class SystemRecalculationIgnored implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $attemptedValue,
        public DateTimeImmutable $occurredAt,
    ) {}
}
