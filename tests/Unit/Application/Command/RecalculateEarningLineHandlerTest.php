<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Application\Command;

use Alcor\Payroll\Application\Command\CalculateEarningLine;
use Alcor\Payroll\Application\Command\CalculateEarningLineHandler;
use Alcor\Payroll\Application\Command\RecalculateEarningLine;
use Alcor\Payroll\Application\Command\RecalculateEarningLineHandler;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Alcor\Payroll\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\FrozenClock;
use Alcor\Payroll\Tests\Support\TestIds;
use PHPUnit\Framework\TestCase;

final class RecalculateEarningLineHandlerTest extends TestCase
{
    private EventSourcedEarningLineRepository $lines;

    private RecalculateEarningLineHandler $handle;

    protected function setUp(): void
    {
        $this->lines = new EventSourcedEarningLineRepository(new InMemoryEventStore());
        $clock = new FrozenClock();
        (new CalculateEarningLineHandler($this->lines, $clock))(
            new CalculateEarningLine(TestIds::LINE, '1000.00', 'USD'),
        );
        $this->handle = new RecalculateEarningLineHandler($this->lines, $clock);
    }

    public function test_it_moves_a_line_no_one_has_corrected(): void
    {
        ($this->handle)(new RecalculateEarningLine(TestIds::LINE, '1050.00', 'USD'));

        self::assertSame(105_000, $this->lines->get(new EarningLineId(TestIds::LINE))->currentValue()->minor);
    }

    public function test_recalculating_a_line_that_does_not_exist_fails(): void
    {
        $this->expectException(EarningLineNotFound::class);

        ($this->handle)(new RecalculateEarningLine(TestIds::LINE_OTHER, '1050.00', 'USD'));
    }
}
