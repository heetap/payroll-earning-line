<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

/**
 * Primitives on purpose: a command is a message that would arrive over a
 * transport, already serialized. Turning it into value objects is the
 * handler's job, and that is where invalid input first raises.
 */
final readonly class CalculateEarningLine
{
    public function __construct(
        public string $lineId,
        public string $amount,
        public string $currency,
    ) {}
}
