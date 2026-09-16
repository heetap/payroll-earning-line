<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

final readonly class GetEarningLineAudit
{
    public function __construct(public string $lineId) {}
}
