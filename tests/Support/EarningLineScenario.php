<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Support;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\Comment;
use Alcor\Payroll\Domain\EarningLine\EarningLine;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Alcor\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Alcor\Payroll\Domain\EarningLine\SpecialistId;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Alcor\Payroll\Domain\Shared\Money;
use DateTimeImmutable;

/**
 * Builds the "given" half of a given/when/then aggregate test: past events,
 * and a line reconstituted from them.
 */
final class EarningLineScenario
{
    public const string AT = '2026-03-01T09:00:00+00:00';

    public static function at(string $iso = self::AT): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }

    public static function calculated(string $amount = '1000.00', Currency $currency = Currency::USD): EarningLineCalculated
    {
        return new EarningLineCalculated(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal($amount, $currency),
            self::at(),
        );
    }

    public static function recalculated(string $amount, Currency $currency = Currency::USD): EarningLineRecalculated
    {
        return new EarningLineRecalculated(
            new EarningLineId(TestIds::LINE),
            Money::fromDecimal($amount, $currency),
            self::at(),
        );
    }

    public static function adjusted(
        int $number,
        string $amount,
        string $comment = 'correction',
        Currency $currency = Currency::USD,
    ): ManualAdjustmentAdded {
        return new ManualAdjustmentAdded(
            new EarningLineId(TestIds::LINE),
            new AdjustmentNumber($number),
            Money::fromDecimal($amount, $currency),
            new Comment($comment),
            new SpecialistId(TestIds::SPECIALIST),
            self::at(),
        );
    }

    public static function lineWith(DomainEvent ...$events): EarningLine
    {
        return EarningLine::reconstitute(new EarningLineId(TestIds::LINE), $events);
    }
}
