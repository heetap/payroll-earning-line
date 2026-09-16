<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

use Alcor\Payroll\Domain\EarningLine\AdjustmentNumber;
use Alcor\Payroll\Domain\EarningLine\Comment;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\EarningLineRepository;
use Alcor\Payroll\Domain\EarningLine\SpecialistId;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Psr\Clock\ClockInterface;

final class AddManualAdjustmentHandler
{
    public function __construct(
        private readonly EarningLineRepository $lines,
        private readonly ClockInterface $clock,
    ) {}

    public function __invoke(AddManualAdjustment $command): void
    {
        $line = $this->lines->get(new EarningLineId($command->lineId));

        // The aggregate returns the number it assigned; a command handler
        // answers nothing, so callers read it back through the audit query.
        $line->addAdjustment(
            Money::fromDecimal($command->amount, Currency::fromCode($command->currency)),
            new Comment($command->comment),
            new SpecialistId($command->specialistId),
            $this->clock->now(),
            $command->compensates === null ? null : new AdjustmentNumber($command->compensates),
        );

        $this->lines->save($line);
    }
}
