<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Application\Query;

use Alcor\Payroll\Application\Query\AdjustmentEntry;
use Alcor\Payroll\Application\Query\AuditHistoryProjection;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Alcor\Payroll\Tests\Support\EarningLineScenario;
use Alcor\Payroll\Tests\Support\TestIds;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AuditHistoryProjectionTest extends TestCase
{
    private AuditHistoryProjection $project;

    private EarningLineId $id;

    protected function setUp(): void
    {
        $this->project = new AuditHistoryProjection();
        $this->id = new EarningLineId(TestIds::LINE);
    }

    public function test_nothing_is_frozen_before_the_first_correction(): void
    {
        $view = $this->project->project($this->id, [
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::recalculated('1050.00'),
        ]);

        self::assertNull($view->frozenSystemValue, 'no correction exists, so nothing is frozen yet');
        self::assertSame(105_000, $view->systemValue->minor);
        self::assertSame(105_000, $view->currentValue->minor);
        self::assertSame([], $view->adjustments);
        self::assertSame([], $view->ignoredRecalculations);
    }

    public function test_the_first_correction_freezes_the_system_value(): void
    {
        $view = $this->project->project($this->id, [
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::recalculated('1050.00'),
            EarningLineScenario::adjusted(1, '-45.55', 'Employee declined dental benefit'),
        ]);

        self::assertNotNull($view->frozenSystemValue);
        self::assertSame(105_000, $view->frozenSystemValue->minor);
        self::assertSame(105_000, $view->systemValue->minor);
        self::assertSame(100_445, $view->currentValue->minor);
    }

    public function test_a_line_corrected_without_ever_being_recalculated_freezes_at_its_first_value(): void
    {
        $view = $this->project->project($this->id, [
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '10.00'),
        ]);

        self::assertNotNull($view->frozenSystemValue);
        self::assertSame(100_000, $view->frozenSystemValue->minor);
    }

    public function test_an_ignored_recalculation_moves_neither_value(): void
    {
        $view = $this->project->project($this->id, [
            EarningLineScenario::calculated('1050.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
            new SystemRecalculationIgnored(
                $this->id,
                Money::fromDecimal('1075.00', Currency::USD),
                EarningLineScenario::at(),
            ),
        ]);

        self::assertNotNull($view->frozenSystemValue);
        self::assertSame(105_000, $view->frozenSystemValue->minor);
        self::assertSame(105_000, $view->systemValue->minor);
        self::assertSame(100_445, $view->currentValue->minor);
        self::assertCount(1, $view->ignoredRecalculations);
        self::assertSame(107_500, $view->ignoredRecalculations[0]->attemptedValue->minor);
    }

    public function test_ignored_recalculations_are_not_part_of_the_adjustment_history(): void
    {
        $view = $this->project->project($this->id, [
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '10.00'),
            new SystemRecalculationIgnored(
                $this->id,
                Money::fromDecimal('2000.00', Currency::USD),
                EarningLineScenario::at(),
            ),
        ]);

        self::assertCount(1, $view->adjustments);
    }

    public function test_it_reports_every_correction_in_the_order_they_were_made(): void
    {
        $view = $this->project->project($this->id, [
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-45.55', 'First'),
            EarningLineScenario::adjusted(2, '-0.20', 'Second'),
            EarningLineScenario::adjusted(3, '0.20', 'Correcting mistake in adjustment #2', 2),
        ]);

        self::assertCount(3, $view->adjustments);
        self::assertSame(
            [1, 2, 3],
            array_map(static fn(AdjustmentEntry $entry): int => $entry->number, $view->adjustments),
        );
        self::assertSame('First', $view->adjustments[0]->comment);
        self::assertSame(TestIds::SPECIALIST, $view->adjustments[0]->by);
        self::assertNull($view->adjustments[0]->compensates);
        self::assertSame(2, $view->adjustments[2]->compensates);
        self::assertSame(95_445, $view->currentValue->minor);
    }

    public function test_a_stream_that_does_not_start_with_a_calculation_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        $this->project->project($this->id, [EarningLineScenario::adjusted(1, '10.00')]);
    }
}
