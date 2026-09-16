<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Alcor\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Alcor\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Alcor\Payroll\Domain\Shared\Money;
use LogicException;

final class AuditHistoryProjection
{
    /**
     * The fold is an if/continue chain rather than the aggregate's
     * match (true): each arm here advances up to five accumulators at once
     * ($systemValue, $frozenSystemValue, $currentValue, $adjustments,
     * $ignoredRecalculations), and a match arm's expression cannot carry
     * that — it produces one value, not a set of side effects.
     *
     * @param iterable<DomainEvent> $events
     */
    public function project(EarningLineId $id, iterable $events): AuditHistoryView
    {
        $systemValue = null;
        $frozenSystemValue = null;
        $currentValue = null;
        $adjustments = [];
        $ignoredRecalculations = [];

        foreach ($events as $event) {
            if ($event instanceof EarningLineCalculated) {
                $systemValue = $event->systemValue;
                $currentValue = $event->systemValue;

                continue;
            }

            if ($event instanceof EarningLineRecalculated) {
                $systemValue = $event->newSystemValue;
                $currentValue = $event->newSystemValue;

                continue;
            }

            if ($event instanceof ManualAdjustmentAdded) {
                // The value in effect at the first correction is the one that
                // freezes; later corrections leave it alone.
                $frozenSystemValue ??= $this->started($systemValue);
                $currentValue = $this->started($currentValue)->add($event->amount);

                $adjustments[] = new AdjustmentEntry(
                    $event->number->value,
                    $event->amount,
                    $event->comment->value,
                    $event->by->value,
                    $event->occurredAt,
                    $event->compensates?->value,
                );

                continue;
            }

            if ($event instanceof SystemRecalculationIgnored) {
                $ignoredRecalculations[] = new IgnoredRecalculation($event->attemptedValue, $event->occurredAt);

                continue;
            }

            throw new LogicException(sprintf('Unhandled event %s.', $event::class));
        }

        return new AuditHistoryView(
            $id->value,
            $this->started($systemValue),
            $frozenSystemValue,
            $adjustments,
            $ignoredRecalculations,
            $this->started($currentValue),
        );
    }

    private function started(?Money $value): Money
    {
        return $value ?? throw new LogicException('An earning line stream must begin with EarningLineCalculated.');
    }
}
