<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Infrastructure\Cli;

use Alcor\Payroll\Application\Query\AdjustmentEntry;
use Alcor\Payroll\Application\Query\AuditHistoryView;
use Alcor\Payroll\Application\Query\IgnoredRecalculation;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\Money;
use Alcor\Payroll\Infrastructure\Cli\AuditTableRenderer;
use Alcor\Payroll\Infrastructure\Cli\MoneyFormatter;
use Alcor\Payroll\Tests\Support\TestIds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuditTableRendererTest extends TestCase
{
    private AuditTableRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new AuditTableRenderer(new MoneyFormatter());
    }

    public function test_a_positive_adjustment_renders_with_a_leading_plus(): void
    {
        $table = $this->renderer->render($this->view(
            frozenSystemValue: Money::fromDecimal('1050.00', Currency::USD),
            adjustments: [$this->adjustment(1, '100.10')],
        ));

        self::assertStringContainsString('+$100.10', $table);
    }

    public function test_a_negative_adjustment_renders_with_a_minus_and_no_plus(): void
    {
        $table = $this->renderer->render($this->view(
            frozenSystemValue: Money::fromDecimal('1050.00', Currency::USD),
            adjustments: [$this->adjustment(1, '-45.55')],
        ));

        self::assertStringContainsString('-$45.55', $table);
        self::assertStringNotContainsString('+-$45.55', $table);
        self::assertStringNotContainsString('+$45.55', $table);
    }

    public function test_absolute_amounts_never_carry_a_leading_plus(): void
    {
        $table = $this->renderer->render($this->view(
            frozenSystemValue: Money::fromDecimal('1050.00', Currency::USD),
            adjustments: [$this->adjustment(1, '100.10')],
            ignoredRecalculations: [
                new IgnoredRecalculation(Money::fromDecimal('1075.00', Currency::USD), new DateTimeImmutable('2026-01-01')),
            ],
            currentValue: Money::fromDecimal('1150.10', Currency::USD),
        ));

        // The frozen system value, the ignored recalculation and the current
        // value are absolute amounts, not deltas: a leading '+' would be
        // wrong on any of them. This is what would fail if the sign were
        // ever pushed down into MoneyFormatter instead of staying here.
        self::assertStringContainsString('$1,050.00', $table);
        self::assertStringNotContainsString('+$1,050.00', $table);
        self::assertStringContainsString('$1,075.00', $table);
        self::assertStringNotContainsString('+$1,075.00', $table);
        self::assertStringContainsString('$1,150.10', $table);
        self::assertStringNotContainsString('+$1,150.10', $table);
    }

    public function test_a_live_system_value_renders_without_claiming_a_freeze(): void
    {
        $table = $this->renderer->render($this->view(frozenSystemValue: null));

        self::assertStringContainsString('System value (live)', $table);
        self::assertStringContainsString('no manual correction yet', $table);
        self::assertStringNotContainsString('System value (frozen)', $table);
        self::assertStringNotContainsString('frozen at the first correction', $table);
    }

    private function adjustment(int $number, string $amount, ?int $compensates = null): AdjustmentEntry
    {
        return new AdjustmentEntry(
            $number,
            Money::fromDecimal($amount, Currency::USD),
            'a comment',
            TestIds::SPECIALIST,
            new DateTimeImmutable('2026-01-01'),
            $compensates,
        );
    }

    /**
     * @param list<AdjustmentEntry>      $adjustments
     * @param list<IgnoredRecalculation> $ignoredRecalculations
     */
    private function view(
        ?Money $frozenSystemValue,
        array $adjustments = [],
        array $ignoredRecalculations = [],
        ?Money $currentValue = null,
    ): AuditHistoryView {
        $systemValue = Money::fromDecimal('1000.00', Currency::USD);

        return new AuditHistoryView(
            TestIds::LINE,
            $systemValue,
            $frozenSystemValue,
            $adjustments,
            $ignoredRecalculations,
            $currentValue ?? $systemValue,
        );
    }
}
