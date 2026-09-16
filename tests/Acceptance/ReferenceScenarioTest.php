<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Acceptance;

use Alcor\Payroll\Application\Command\AddManualAdjustment;
use Alcor\Payroll\Application\Command\AddManualAdjustmentHandler;
use Alcor\Payroll\Application\Command\CalculateEarningLine;
use Alcor\Payroll\Application\Command\CalculateEarningLineHandler;
use Alcor\Payroll\Application\Command\RecalculateEarningLine;
use Alcor\Payroll\Application\Command\RecalculateEarningLineHandler;
use Alcor\Payroll\Application\Query\AdjustmentEntry;
use Alcor\Payroll\Application\Query\AuditHistoryProjection;
use Alcor\Payroll\Application\Query\GetEarningLineAudit;
use Alcor\Payroll\Application\Query\GetEarningLineAuditHandler;
use Alcor\Payroll\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\FrozenClock;
use Alcor\Payroll\Tests\Support\TestIds;
use PHPUnit\Framework\TestCase;

/**
 * The worked example from the brief, replayed through the application layer.
 * Its expectations are the contract with the business and are never adjusted
 * to accommodate the code.
 */
final class ReferenceScenarioTest extends TestCase
{
    private CalculateEarningLineHandler $calculate;

    private RecalculateEarningLineHandler $recalculate;

    private AddManualAdjustmentHandler $adjust;

    private GetEarningLineAuditHandler $audit;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $store = new InMemoryEventStore();
        $lines = new EventSourcedEarningLineRepository($store);
        $this->clock = new FrozenClock();

        $this->calculate = new CalculateEarningLineHandler($lines, $this->clock);
        $this->recalculate = new RecalculateEarningLineHandler($lines, $this->clock);
        $this->adjust = new AddManualAdjustmentHandler($lines, $this->clock);
        $this->audit = new GetEarningLineAuditHandler($store, new AuditHistoryProjection());
    }

    public function test_the_reference_scenario_produces_the_expected_numbers(): void
    {
        $this->replayTheScenario();
    }

    private function replayTheScenario(): void
    {
        // Step 1 — the system calculates the line.
        ($this->calculate)(new CalculateEarningLine(TestIds::LINE, '1000.00', 'USD'));
        self::assertSame(100_000, $this->currentValue(), 'step 1: $1,000.00');

        // Step 2 — source data changes; no correction yet, so this is allowed.
        $this->clock->advance(60);
        ($this->recalculate)(new RecalculateEarningLine(TestIds::LINE, '1050.00', 'USD'));
        self::assertSame(105_000, $this->currentValue(), 'step 2: $1,050.00');

        // Step 3 — the specialist corrects the line for the first time.
        $this->clock->advance(60);
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '-45.55',
            'USD',
            'Employee declined dental benefit; reversing deduction',
            TestIds::SPECIALIST,
        ));
        self::assertSame(100_445, $this->currentValue(), 'step 3: $1,004.45');

        // Step 4 — source data changes again; the line is frozen, so this is ignored.
        $this->clock->advance(60);
        ($this->recalculate)(new RecalculateEarningLine(TestIds::LINE, '1075.00', 'USD'));
        self::assertSame(100_445, $this->currentValue(), 'step 4: unchanged at $1,004.45');

        // Step 5 — a late correction.
        $this->clock->advance(60);
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '100.10',
            'USD',
            'Late correction: missed approved overtime bonus',
            TestIds::SPECIALIST,
        ));
        self::assertSame(110_455, $this->currentValue(), 'step 5: $1,104.55');

        // Step 6 — a rounding correction.
        $this->clock->advance(60);
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '-0.10',
            'USD',
            'Minor rounding adjustment',
            TestIds::SPECIALIST,
        ));
        self::assertSame(110_445, $this->currentValue(), 'step 6: $1,104.45');

        // Step 7 — another rounding correction, which turns out to be a mistake.
        $this->clock->advance(60);
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '-0.20',
            'USD',
            'Second minor rounding adjustment',
            TestIds::SPECIALIST,
        ));
        self::assertSame(110_425, $this->currentValue(), 'step 7: $1,104.25');

        // Step 8 — the mistake is fixed by a compensating correction, not an edit.
        $this->clock->advance(60);
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '0.20',
            'USD',
            'Correcting mistake in adjustment #4',
            TestIds::SPECIALIST,
            4,
        ));
        self::assertSame(110_445, $this->currentValue(), 'step 8: $1,104.45');
    }

    public function test_the_reference_scenario_produces_the_expected_audit_history(): void
    {
        $this->replayTheScenario();

        $view = ($this->audit)(new GetEarningLineAudit(TestIds::LINE));

        self::assertNotNull($view->frozenSystemValue);
        self::assertSame(105_000, $view->frozenSystemValue->minor, 'system value frozen at step 3');

        self::assertSame(
            [-4_555, 10_010, -10, -20, 20],
            array_map(static fn(AdjustmentEntry $entry): int => $entry->amount->minor, $view->adjustments),
        );
        self::assertSame(
            [1, 2, 3, 4, 5],
            array_map(static fn(AdjustmentEntry $entry): int => $entry->number, $view->adjustments),
        );
        self::assertSame(
            [null, null, null, null, 4],
            array_map(static fn(AdjustmentEntry $entry): ?int => $entry->compensates, $view->adjustments),
        );
        self::assertSame(
            'Correcting mistake in adjustment #4',
            $view->adjustments[4]->comment,
        );

        self::assertCount(1, $view->ignoredRecalculations, 'step 4 was refused and recorded');
        self::assertSame(107_500, $view->ignoredRecalculations[0]->attemptedValue->minor);

        self::assertSame(110_445, $view->currentValue->minor, 'current value $1,104.45');
    }

    private function currentValue(): int
    {
        return ($this->audit)(new GetEarningLineAudit(TestIds::LINE))->currentValue->minor;
    }
}
