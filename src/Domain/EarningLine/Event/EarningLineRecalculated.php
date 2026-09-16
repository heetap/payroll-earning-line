<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Event;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Alcor\Payroll\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class EarningLineRecalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $newSystemValue,
        public DateTimeImmutable $occurredAt,
    ) {}
}
