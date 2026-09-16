<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Integration;

use Alcor\Payroll\Application\Port\ConcurrencyConflict;
use Alcor\Payroll\Domain\EarningLine\EarningLine;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Alcor\Payroll\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\EarningLineScenario;
use Alcor\Payroll\Tests\Support\TestIds;
use PHPUnit\Framework\TestCase;

final class EventSourcedEarningLineRepositoryTest extends TestCase
{
    private InMemoryEventStore $store;

    private EventSourcedEarningLineRepository $lines;

    protected function setUp(): void
    {
        $this->store = new InMemoryEventStore();
        $this->lines = new EventSourcedEarningLineRepository($this->store);
    }

    public function test_a_saved_line_comes_back_with_the_same_value(): void
    {
        $line = EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        );
        $this->lines->save($line);

        self::assertSame(100_000, $this->lines->get(new EarningLineId(TestIds::LINE))->currentValue()->minor);
    }

    public function test_asking_for_a_line_that_was_never_calculated_fails(): void
    {
        $this->expectException(EarningLineNotFound::class);

        $this->lines->get(new EarningLineId(TestIds::LINE));
    }

    public function test_it_reports_whether_a_line_exists(): void
    {
        self::assertFalse($this->lines->exists(new EarningLineId(TestIds::LINE)));

        $this->lines->save(EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        ));

        self::assertTrue($this->lines->exists(new EarningLineId(TestIds::LINE)));
    }

    public function test_saving_appends_rather_than_replacing(): void
    {
        $this->lines->save(EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        ));

        $line = $this->lines->get(new EarningLineId(TestIds::LINE));
        $line->recalculate(Money::fromDecimal('1050.00', Currency::USD), EarningLineScenario::at());
        $this->lines->save($line);

        self::assertCount(2, $this->store->load(TestIds::LINE));
    }

    public function test_saving_a_line_with_nothing_to_record_is_a_no_op(): void
    {
        $this->lines->save(EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        ));

        $line = $this->lines->get(new EarningLineId(TestIds::LINE));
        $line->recalculate(Money::fromDecimal('1000.00', Currency::USD), EarningLineScenario::at());
        $this->lines->save($line);

        self::assertCount(1, $this->store->load(TestIds::LINE));
    }

    public function test_two_specialists_correcting_the_same_line_at_once_is_refused(): void
    {
        $this->lines->save(EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        ));

        $alice = $this->lines->get(new EarningLineId(TestIds::LINE));
        $bob = $this->lines->get(new EarningLineId(TestIds::LINE));

        $alice->recalculate(Money::fromDecimal('1050.00', Currency::USD), EarningLineScenario::at());
        $bob->recalculate(Money::fromDecimal('1100.00', Currency::USD), EarningLineScenario::at());

        $this->lines->save($alice);

        $this->expectException(ConcurrencyConflict::class);

        $this->lines->save($bob);
    }
}
