<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\EarningLine;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Alcor\Payroll\Domain\Shared\Exception\CurrencyMismatch;
use Alcor\Payroll\Domain\Shared\Money;
use Alcor\Payroll\Tests\Support\EarningLineScenario;
use Alcor\Payroll\Tests\Support\TestIds;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class EarningLineTest extends TestCase
{
    public function test_calculating_a_line_records_the_system_value(): void
    {
        $line = EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        );

        $events = $line->pullRecordedEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(EarningLineCalculated::class, $events[0]);
        self::assertSame(100_000, $events[0]->systemValue->minor);
        self::assertSame(100_000, $line->currentValue()->minor);
    }

    public function test_recalculation_is_allowed_while_no_one_has_corrected_the_line(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        $line->recalculate(Money::fromDecimal('1050.00', Currency::USD), EarningLineScenario::at());

        $events = $line->pullRecordedEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(EarningLineRecalculated::class, $events[0]);
        self::assertSame(105_000, $events[0]->newSystemValue->minor);
        self::assertSame(105_000, $line->currentValue()->minor);
    }

    public function test_recalculating_to_the_same_value_records_nothing(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        $line->recalculate(Money::fromDecimal('1000.00', Currency::USD), EarningLineScenario::at());

        self::assertSame([], $line->pullRecordedEvents());
    }

    public function test_a_line_carries_one_currency_for_its_lifetime(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        $this->expectException(CurrencyMismatch::class);

        $line->recalculate(Money::fromDecimal('1050.00', Currency::EUR), EarningLineScenario::at());
    }

    public function test_a_refused_recalculation_records_nothing(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        try {
            $line->recalculate(Money::fromDecimal('1050.00', Currency::EUR), EarningLineScenario::at());
        } catch (CurrencyMismatch) {
            // asserted in the test above; here only the absence of an event matters
        }

        self::assertSame([], $line->pullRecordedEvents());
    }

    public function test_replaying_events_yields_the_same_value_as_living_through_them(): void
    {
        $live = EarningLine::calculate(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal('1000.00', Currency::USD),
            EarningLineScenario::at(),
        );
        $live->recalculate(Money::fromDecimal('1050.00', Currency::USD), EarningLineScenario::at());

        $replayed = EarningLine::reconstitute(new EarningLineId(TestIds::LINE), $live->pullRecordedEvents());

        self::assertSame(105_000, $replayed->currentValue()->minor);
    }

    public function test_version_counts_only_the_events_the_line_was_loaded_with(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::recalculated('1050.00'),
        );

        self::assertSame(2, $line->version());

        $line->recalculate(Money::fromDecimal('1100.00', Currency::USD), EarningLineScenario::at());

        self::assertSame(2, $line->version(), 'pending events do not advance the persisted version');
    }

    public function test_pulling_recorded_events_empties_them(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));
        $line->recalculate(Money::fromDecimal('1050.00', Currency::USD), EarningLineScenario::at());

        self::assertCount(1, $line->pullRecordedEvents());
        self::assertSame([], $line->pullRecordedEvents());
    }

    public function test_an_unknown_event_type_fails_loudly_instead_of_being_ignored(): void
    {
        $stranger = new class (EarningLineScenario::at()) implements DomainEvent {
            public function __construct(public DateTimeImmutable $occurredAt) {}
        };

        $this->expectException(LogicException::class);

        EarningLine::reconstitute(new EarningLineId(TestIds::LINE), [$stranger]);
    }
}
