<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared;

use DateTimeImmutable;

/**
 * A fact that already happened. Named in the past tense, immutable, and
 * carrying only what is needed to rebuild state.
 */
interface DomainEvent
{
    public DateTimeImmutable $occurredAt { get; }
}
