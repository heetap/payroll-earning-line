<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/** The only place in the codebase that reads the wall clock. */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
