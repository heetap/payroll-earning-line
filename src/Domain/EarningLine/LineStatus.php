<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

enum LineStatus
{
    case SystemCalculated;
    case ManuallyAdjusted;
}
