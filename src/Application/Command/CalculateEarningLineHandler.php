<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

use Alcor\Payroll\Domain\EarningLine\EarningLine;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\EarningLineRepository;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineAlreadyExists;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Psr\Clock\ClockInterface;

final class CalculateEarningLineHandler
{
    public function __construct(
        private readonly EarningLineRepository $lines,
        private readonly ClockInterface $clock,
    ) {}

    public function __invoke(CalculateEarningLine $command): void
    {
        $id = new EarningLineId($command->lineId);

        // Checked here rather than left to the append: a duplicate creation is
        // not a race, and reporting it as one would make ConcurrencyConflict
        // mean two different things.
        if ($this->lines->exists($id)) {
            throw new EarningLineAlreadyExists(
                sprintf('Earning line %s has already been calculated.', $id->value),
            );
        }

        $this->lines->save(EarningLine::calculate(
            $id,
            Money::fromDecimal($command->amount, Currency::fromCode($command->currency)),
            $this->clock->now(),
        ));
    }
}
