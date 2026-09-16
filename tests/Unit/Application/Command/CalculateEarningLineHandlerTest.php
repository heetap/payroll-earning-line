<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Application\Command;

use Alcor\Payroll\Application\Command\CalculateEarningLine;
use Alcor\Payroll\Application\Command\CalculateEarningLineHandler;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineAlreadyExists;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidEarningLineId;
use Alcor\Payroll\Domain\Shared\Exception\InvalidMoneyAmount;
use Alcor\Payroll\Domain\Shared\Exception\UnsupportedCurrency;
use Alcor\Payroll\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\FrozenClock;
use Alcor\Payroll\Tests\Support\TestIds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CalculateEarningLineHandlerTest extends TestCase
{
    private InMemoryEventStore $store;

    private EventSourcedEarningLineRepository $lines;

    private CalculateEarningLineHandler $handle;

    protected function setUp(): void
    {
        $this->store = new InMemoryEventStore();
        $this->lines = new EventSourcedEarningLineRepository($this->store);
        $this->handle = new CalculateEarningLineHandler($this->lines, new FrozenClock());
    }

    public function test_it_calculates_a_line_the_system_has_not_seen_before(): void
    {
        ($this->handle)(new CalculateEarningLine(TestIds::LINE, '1000.00', 'USD'));

        self::assertSame(100_000, $this->lines->get(new EarningLineId(TestIds::LINE))->currentValue()->minor);
    }

    public function test_it_stamps_the_event_with_the_injected_clock(): void
    {
        $at = new DateTimeImmutable('2026-07-04T12:34:56+00:00');
        $handle = new CalculateEarningLineHandler($this->lines, new FrozenClock($at));

        $handle(new CalculateEarningLine(TestIds::LINE, '1000.00', 'USD'));

        self::assertEquals($at, $this->store->load(TestIds::LINE)[0]->occurredAt);
    }

    public function test_calculating_the_same_line_twice_is_refused_as_a_duplicate(): void
    {
        ($this->handle)(new CalculateEarningLine(TestIds::LINE, '1000.00', 'USD'));

        $this->expectException(EarningLineAlreadyExists::class);

        ($this->handle)(new CalculateEarningLine(TestIds::LINE, '2000.00', 'USD'));
    }

    public function test_a_malformed_identifier_is_refused_at_the_boundary(): void
    {
        $this->expectException(InvalidEarningLineId::class);

        ($this->handle)(new CalculateEarningLine('line-1', '1000.00', 'USD'));
    }

    public function test_a_malformed_amount_is_refused_at_the_boundary(): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        ($this->handle)(new CalculateEarningLine(TestIds::LINE, '1.005', 'USD'));
    }

    public function test_an_unsupported_currency_is_refused_as_a_domain_error(): void
    {
        $this->expectException(UnsupportedCurrency::class);

        ($this->handle)(new CalculateEarningLine(TestIds::LINE, '1000.00', 'XYZ'));
    }
}
