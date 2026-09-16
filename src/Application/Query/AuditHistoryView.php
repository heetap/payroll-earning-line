<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

use Alcor\Payroll\Domain\Shared\Money;

final readonly class AuditHistoryView
{
    /**
     * @param list<AdjustmentEntry>      $adjustments
     * @param list<IgnoredRecalculation> $ignoredRecalculations
     */
    public function __construct(
        public string $lineId,
        public Money $systemValue,
        // Null until the first correction: calling the live value "frozen"
        // would misstate the one rule this audit exists to evidence.
        public ?Money $frozenSystemValue,
        public array $adjustments,
        public array $ignoredRecalculations,
        public Money $currentValue,
    ) {}
}
