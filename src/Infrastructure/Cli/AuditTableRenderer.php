<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Cli;

use Alcor\Payroll\Application\Query\AuditHistoryView;

final class AuditTableRenderer
{
    public function __construct(private readonly MoneyFormatter $money) {}

    public function render(AuditHistoryView $view): string
    {
        $rows = [['Entry', 'Value', 'Comment']];

        $rows[] = [
            $view->frozenSystemValue === null ? 'System value (live)' : 'System value (frozen)',
            $this->money->format($view->frozenSystemValue ?? $view->systemValue),
            $view->frozenSystemValue === null ? 'no manual correction yet' : 'frozen at the first correction',
        ];

        foreach ($view->adjustments as $entry) {
            $rows[] = [
                sprintf('Adjustment %d', $entry->number),
                // An adjustment is a signed delta, unlike every other row in
                // this table: the '+' is added here, not in MoneyFormatter,
                // because a leading '+' on a system value or a current value
                // would be wrong. Zero cannot occur (ZeroAdjustmentNotAllowed).
                ($entry->amount->minor > 0 ? '+' : '') . $this->money->format($entry->amount),
                $entry->compensates === null
                    ? $entry->comment
                    : sprintf('%s (compensates #%d)', $entry->comment, $entry->compensates),
            ];
        }

        foreach ($view->ignoredRecalculations as $ignored) {
            $rows[] = [
                'Recalculation ignored',
                $this->money->format($ignored->attemptedValue),
                'attempted by the system, refused by the freeze rule',
            ];
        }

        $rows[] = ['Current value', $this->money->format($view->currentValue), ''];

        return $this->table($rows);
    }

    /** @param list<array{string, string, string}> $rows */
    private function table(array $rows): string
    {
        $widths = [0, 0, 0];

        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                $widths[$column] = max($widths[$column], mb_strlen($cell));
            }
        }

        $rule = '+' . implode('+', array_map(static fn(int $w): string => str_repeat('-', $w + 2), $widths)) . '+';
        $lines = [$rule];

        foreach ($rows as $index => $row) {
            $cells = [];

            foreach ($row as $column => $cell) {
                $cells[] = ' ' . $cell . str_repeat(' ', $widths[$column] - mb_strlen($cell)) . ' ';
            }

            $lines[] = '|' . implode('|', $cells) . '|';

            if ($index === 0) {
                $lines[] = $rule;
            }
        }

        $lines[] = $rule;

        return implode("\n", $lines) . "\n";
    }
}
