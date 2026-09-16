<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Application\Command;

use Alcor\Payroll\Application\Command\AddManualAdjustment;
use Alcor\Payroll\Application\Command\AddManualAdjustmentHandler;
use Alcor\Payroll\Application\Command\CalculateEarningLine;
use Alcor\Payroll\Application\Command\CalculateEarningLineHandler;
use Alcor\Payroll\Application\Command\RecalculateEarningLine;
use Alcor\Payroll\Application\Command\RecalculateEarningLineHandler;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidComment;
use Alcor\Payroll\Domain\EarningLine\Exception\UnknownAdjustment;
use Alcor\Payroll\Domain\EarningLine\Exception\ZeroAdjustmentNotAllowed;
use Alcor\Payroll\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\FrozenClock;
use Alcor\Payroll\Tests\Support\TestIds;
use PHPUnit\Framework\TestCase;

final class AddManualAdjustmentHandlerTest extends TestCase
{
    private EventSourcedEarningLineRepository $lines;

    private AddManualAdjustmentHandler $adjust;

    private RecalculateEarningLineHandler $recalculate;

    protected function setUp(): void
    {
        $this->lines = new EventSourcedEarningLineRepository(new InMemoryEventStore());
        $clock = new FrozenClock();
        (new CalculateEarningLineHandler($this->lines, $clock))(
            new CalculateEarningLine(TestIds::LINE, '1000.00', 'USD'),
        );
        $this->adjust = new AddManualAdjustmentHandler($this->lines, $clock);
        $this->recalculate = new RecalculateEarningLineHandler($this->lines, $clock);
    }

    public function test_it_applies_a_signed_correction(): void
    {
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '-45.55',
            'USD',
            'Employee declined dental benefit; reversing deduction',
            TestIds::SPECIALIST,
        ));

        self::assertSame(95_445, $this->lines->get(new EarningLineId(TestIds::LINE))->currentValue()->minor);
    }

    public function test_a_corrected_line_stops_responding_to_recalculation(): void
    {
        ($this->adjust)(new AddManualAdjustment(TestIds::LINE, '10.00', 'USD', 'Late bonus', TestIds::SPECIALIST));
        ($this->recalculate)(new RecalculateEarningLine(TestIds::LINE, '9999.00', 'USD'));

        self::assertSame(101_000, $this->lines->get(new EarningLineId(TestIds::LINE))->currentValue()->minor);
    }

    public function test_a_correction_without_an_explanation_is_refused(): void
    {
        $this->expectException(InvalidComment::class);

        ($this->adjust)(new AddManualAdjustment(TestIds::LINE, '10.00', 'USD', '   ', TestIds::SPECIALIST));
    }

    public function test_a_correction_of_nothing_is_refused(): void
    {
        $this->expectException(ZeroAdjustmentNotAllowed::class);

        ($this->adjust)(new AddManualAdjustment(TestIds::LINE, '0.00', 'USD', 'no-op', TestIds::SPECIALIST));
    }

    public function test_it_links_a_correction_to_the_adjustment_it_fixes(): void
    {
        ($this->adjust)(new AddManualAdjustment(TestIds::LINE, '-0.20', 'USD', 'Rounding', TestIds::SPECIALIST));
        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '0.20',
            'USD',
            'Correcting mistake in adjustment #1',
            TestIds::SPECIALIST,
            1,
        ));

        self::assertSame(100_000, $this->lines->get(new EarningLineId(TestIds::LINE))->currentValue()->minor);
    }

    public function test_a_correction_pointing_at_a_missing_adjustment_is_refused(): void
    {
        $this->expectException(UnknownAdjustment::class);

        ($this->adjust)(new AddManualAdjustment(
            TestIds::LINE,
            '0.20',
            'USD',
            'Correcting mistake in adjustment #9',
            TestIds::SPECIALIST,
            9,
        ));
    }
}
