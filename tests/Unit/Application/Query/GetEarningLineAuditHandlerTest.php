<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Application\Query;

use Alcor\Payroll\Application\Query\AuditHistoryProjection;
use Alcor\Payroll\Application\Query\GetEarningLineAudit;
use Alcor\Payroll\Application\Query\GetEarningLineAuditHandler;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;
use Alcor\Payroll\Tests\Support\EarningLineScenario;
use Alcor\Payroll\Tests\Support\TestIds;
use PHPUnit\Framework\TestCase;

final class GetEarningLineAuditHandlerTest extends TestCase
{
    public function test_it_answers_from_the_event_stream(): void
    {
        $store = new InMemoryEventStore();
        $store->append(TestIds::LINE, 0, [
            EarningLineScenario::calculated('1000.00'),
            EarningLineScenario::adjusted(1, '-45.55'),
        ]);

        $view = (new GetEarningLineAuditHandler($store, new AuditHistoryProjection()))(
            new GetEarningLineAudit(TestIds::LINE),
        );

        self::assertSame(TestIds::LINE, $view->lineId);
        self::assertSame(95_445, $view->currentValue->minor);
    }

    public function test_asking_about_a_line_that_does_not_exist_fails(): void
    {
        $handle = new GetEarningLineAuditHandler(new InMemoryEventStore(), new AuditHistoryProjection());

        $this->expectException(EarningLineNotFound::class);

        $handle(new GetEarningLineAudit(TestIds::LINE));
    }
}
