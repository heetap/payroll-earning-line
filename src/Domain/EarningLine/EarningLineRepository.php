<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;

interface EarningLineRepository
{
    public function exists(EarningLineId $id): bool;

    /** @throws EarningLineNotFound */
    public function get(EarningLineId $id): EarningLine;

    public function save(EarningLine $line): void;
}
