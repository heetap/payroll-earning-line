<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Integration;

use Alcor\Payroll\Application\Port\ConcurrencyConflict;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\EarningLineScenario;
use Alcor\Payroll\Tests\Support\TestIds;
use PHPUnit\Framework\TestCase;

final class InMemoryEventStoreTest extends TestCase
{
    public function test_an_unknown_stream_is_empty(): void
    {
        self::assertSame([], new InMemoryEventStore()->load(TestIds::LINE));
    }

    public function test_it_appends_to_a_new_stream_at_version_zero(): void
    {
        $store = new InMemoryEventStore();

        $store->append(TestIds::LINE, 0, [EarningLineScenario::calculated('1000.00')]);

        self::assertCount(1, $store->load(TestIds::LINE));
    }

    public function test_appending_keeps_earlier_events_and_their_order(): void
    {
        $store = new InMemoryEventStore();
        $store->append(TestIds::LINE, 0, [EarningLineScenario::calculated('1000.00')]);
        $store->append(TestIds::LINE, 1, [EarningLineScenario::recalculated('1050.00')]);

        $stream = $store->load(TestIds::LINE);

        self::assertCount(2, $stream);
        self::assertEquals(EarningLineScenario::calculated('1000.00'), $stream[0]);
    }

    public function test_a_writer_working_from_a_stale_version_is_refused(): void
    {
        $store = new InMemoryEventStore();
        $store->append(TestIds::LINE, 0, [EarningLineScenario::calculated('1000.00')]);

        $this->expectException(ConcurrencyConflict::class);

        $store->append(TestIds::LINE, 0, [EarningLineScenario::recalculated('1050.00')]);
    }

    public function test_a_refused_append_leaves_the_stream_untouched(): void
    {
        $store = new InMemoryEventStore();
        $store->append(TestIds::LINE, 0, [EarningLineScenario::calculated('1000.00')]);

        try {
            $store->append(TestIds::LINE, 0, [EarningLineScenario::recalculated('1050.00')]);
        } catch (ConcurrencyConflict) {
            // the point of the test is what the stream looks like afterwards
        }

        self::assertCount(1, $store->load(TestIds::LINE));
    }

    public function test_streams_do_not_leak_into_each_other(): void
    {
        $store = new InMemoryEventStore();
        $store->append(TestIds::LINE, 0, [EarningLineScenario::calculated('1000.00')]);

        self::assertSame([], $store->load(TestIds::LINE_OTHER));
    }
}
