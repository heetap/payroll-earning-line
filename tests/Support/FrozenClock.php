<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final class FrozenClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable(EarningLineScenario::AT);
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->add(new DateInterval(sprintf('PT%dS', $seconds)));
    }
}
