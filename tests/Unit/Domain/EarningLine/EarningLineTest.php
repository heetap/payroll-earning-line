<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\Comment;
use Alcor\Payroll\Domain\EarningLine\EarningLine;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Alcor\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Alcor\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use Alcor\Payroll\Domain\EarningLine\Exception\UnknownAdjustment;
use Alcor\Payroll\Domain\EarningLine\Exception\ZeroAdjustmentNotAllowed;
use Alcor\Payroll\Domain\EarningLine\SpecialistId;
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

    public function test_an_adjustment_moves_the_current_value_by_its_signed_amount(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::recalculated('1050.00'),
        );

        $number = $line->addAdjustment(
            Money::fromDecimal('-45.55', Currency::USD),
            new Comment('Employee declined dental benefit; reversing deduction'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
        );

        $events = $line->pullRecordedEvents();

        self::assertSame(1, $number->value);
        self::assertCount(1, $events);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[0]);
        self::assertSame(-4_555, $events[0]->amount->minor);
        self::assertSame(TestIds::SPECIALIST, $events[0]->by->value);
        self::assertSame(100_445, $line->currentValue()->minor);
    }

    public function test_adjustments_are_numbered_in_sequence(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
            EarningLineScenario::adjusted(2, '100.10'),
        );

        $number = $line->addAdjustment(
            Money::fromDecimal('-0.10', Currency::USD),
            new Comment('Minor rounding adjustment'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
        );

        self::assertSame(3, $number->value);
    }

    public function test_recalculation_is_ignored_once_line_has_manual_adjustment(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::recalculated('1050.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
        );

        $line->recalculate(Money::fromDecimal('1075.00', Currency::USD), EarningLineScenario::at());

        $events = $line->pullRecordedEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SystemRecalculationIgnored::class, $events[0]);
        self::assertSame(107_500, $events[0]->attemptedValue->minor, 'the attempt is recorded, not applied');
        self::assertSame(100_445, $line->currentValue()->minor, 'the value did not move');
    }

    public function test_the_freeze_survives_any_number_of_later_recalculations(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '10.00'),
        );

        $line->recalculate(Money::fromDecimal('5000.00', Currency::USD), EarningLineScenario::at());
        $line->recalculate(Money::fromDecimal('9000.00', Currency::USD), EarningLineScenario::at());

        self::assertCount(2, $line->pullRecordedEvents());
        self::assertSame(101_000, $line->currentValue()->minor);
    }

    public function test_an_ignored_recalculation_is_recorded_even_when_the_value_would_not_change(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1050.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
        );

        $line->recalculate(Money::fromDecimal('1050.00', Currency::USD), EarningLineScenario::at());

        self::assertCount(1, $line->pullRecordedEvents(), 'the refusal itself is the audit fact');
    }

    public function test_a_frozen_line_still_refuses_a_foreign_currency(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '10.00'),
        );

        $this->expectException(CurrencyMismatch::class);

        $line->recalculate(Money::fromDecimal('1050.00', Currency::EUR), EarningLineScenario::at());
    }

    public function test_an_adjustment_must_change_something(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        $this->expectException(ZeroAdjustmentNotAllowed::class);

        $line->addAdjustment(
            Money::zero(Currency::USD),
            new Comment('no-op'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
        );
    }

    public function test_an_adjustment_must_be_in_the_lines_currency(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        $this->expectException(CurrencyMismatch::class);

        $line->addAdjustment(
            Money::fromDecimal('10.00', Currency::EUR),
            new Comment('wrong currency'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
        );
    }

    public function test_a_line_may_go_negative_because_no_rule_forbids_it(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('10.00'));

        $line->addAdjustment(
            Money::fromDecimal('-50.00', Currency::USD),
            new Comment('Clawback of an overpayment'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
        );

        self::assertSame(-4_000, $line->currentValue()->minor);
    }

    public function test_a_mistake_is_fixed_by_a_new_adjustment_that_points_at_it(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
            EarningLineScenario::adjusted(2, '-0.20'),
        );

        $number = $line->addAdjustment(
            Money::fromDecimal('0.20', Currency::USD),
            new Comment('Correcting mistake in adjustment #2'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
            new AdjustmentNumber(2),
        );

        $events = $line->pullRecordedEvents();

        self::assertSame(3, $number->value);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[0]);
        self::assertNotNull($events[0]->compensates);
        self::assertSame(2, $events[0]->compensates->value);
        // 1000.00 - 45.55 - 0.20 + 0.20
        self::assertSame(95_445, $line->currentValue()->minor);
    }

    public function test_an_ordinary_adjustment_compensates_nothing(): void
    {
        $line = EarningLineScenario::lineWith(EarningLineScenario::calculated('1000.00'));

        $line->addAdjustment(
            Money::fromDecimal('10.00', Currency::USD),
            new Comment('Late bonus'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
        );

        $events = $line->pullRecordedEvents();

        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[0]);
        self::assertNull($events[0]->compensates);
    }

    public function test_a_correction_cannot_point_at_an_adjustment_that_does_not_exist(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
        );

        $this->expectException(UnknownAdjustment::class);

        $line->addAdjustment(
            Money::fromDecimal('0.20', Currency::USD),
            new Comment('Correcting mistake in adjustment #7'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
            new AdjustmentNumber(7),
        );
    }

    public function test_a_correction_cannot_point_at_the_adjustment_being_created(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
        );

        $this->expectException(UnknownAdjustment::class);

        $line->addAdjustment(
            Money::fromDecimal('0.20', Currency::USD),
            new Comment('Correcting itself'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
            new AdjustmentNumber(2),
        );
    }

    public function test_compensation_is_a_link_not_an_enforced_opposite_amount(): void
    {
        $line = EarningLineScenario::lineWith(
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-50.00'),
        );

        $line->addAdjustment(
            Money::fromDecimal('20.00', Currency::USD),
            new Comment('Partially reversing adjustment #1'),
            new SpecialistId(TestIds::SPECIALIST),
            EarningLineScenario::at(),
            new AdjustmentNumber(1),
        );

        self::assertSame(97_000, $line->currentValue()->minor);
    }
}
