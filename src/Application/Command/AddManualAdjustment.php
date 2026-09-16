<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

final readonly class AddManualAdjustment
{
    public function __construct(
        public string $lineId,
        public string $amount,
        public string $currency,
        public string $comment,
        public string $specialistId,
        public ?int $compensates = null,
    ) {}
}
