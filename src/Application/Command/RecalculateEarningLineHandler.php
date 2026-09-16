<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\EarningLineRepository;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Psr\Clock\ClockInterface;

final class RecalculateEarningLineHandler
{
    public function __construct(
        private readonly EarningLineRepository $lines,
        private readonly ClockInterface $clock,
    ) {}

    public function __invoke(RecalculateEarningLine $command): void
    {
        $line = $this->lines->get(new EarningLineId($command->lineId));

        $line->recalculate(
            Money::fromDecimal($command->amount, Currency::fromCode($command->currency)),
            $this->clock->now(),
        );

        $this->lines->save($line);
    }
}
