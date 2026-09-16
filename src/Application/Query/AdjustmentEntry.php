<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

use Alcor\Payroll\Domain\Shared\Money;
use DateTimeImmutable;

/**
 * The read model keeps its own shape rather than reusing a write-model
 * class, so the audit can gain a field without touching the aggregate.
 */
final readonly class AdjustmentEntry
{
    public function __construct(
        public int $number,
        public Money $amount,
        public string $comment,
        public string $by,
        public DateTimeImmutable $at,
        public ?int $compensates,
    ) {}
}
