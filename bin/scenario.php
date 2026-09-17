<?php

declare(strict_types=1);

use Alcor\Payroll\Application\Command\AddManualAdjustment;
use Alcor\Payroll\Application\Command\AddManualAdjustmentHandler;
use Alcor\Payroll\Application\Command\CalculateEarningLine;
use Alcor\Payroll\Application\Command\CalculateEarningLineHandler;
use Alcor\Payroll\Application\Command\RecalculateEarningLine;
use Alcor\Payroll\Application\Command\RecalculateEarningLineHandler;
use Alcor\Payroll\Application\Query\AuditHistoryProjection;
use Alcor\Payroll\Application\Query\GetEarningLineAudit;
use Alcor\Payroll\Application\Query\GetEarningLineAuditHandler;
use Alcor\Payroll\Infrastructure\Cli\AuditTableRenderer;
use Alcor\Payroll\Infrastructure\Cli\MoneyFormatter;
use Alcor\Payroll\Infrastructure\Clock\SystemClock;
use Alcor\Payroll\Infrastructure\Identity\UuidV4;
use Alcor\Payroll\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEventStore;

require __DIR__ . '/../vendor/autoload.php';

// Composition root: everything is wired by hand, so the dependency graph is
// readable top to bottom and there is no container to interrogate.
$store = new InMemoryEventStore();
$lines = new EventSourcedEarningLineRepository($store);
$clock = new SystemClock();

$calculate = new CalculateEarningLineHandler($lines, $clock);
$recalculate = new RecalculateEarningLineHandler($lines, $clock);
$adjust = new AddManualAdjustmentHandler($lines, $clock);
$audit = new GetEarningLineAuditHandler($store, new AuditHistoryProjection());

$money = new MoneyFormatter();
$table = new AuditTableRenderer($money);

$lineId = UuidV4::generate();
$specialist = 'specialist-alice';

$step = static function (string $description) use ($audit, $money, $lineId): void {
    $value = $audit(new GetEarningLineAudit($lineId))->currentValue;
    printf("%-76s %14s\n", $description, $money->format($value));
};

echo "Earning line {$lineId}\n\n";

$calculate(new CalculateEarningLine($lineId, '1000.00', 'USD'));
$step('Step 1  System calculates the line');

$recalculate(new RecalculateEarningLine($lineId, '1050.00', 'USD'));
$step('Step 2  System recalculates (allowed, no correction yet)');

$adjust(new AddManualAdjustment(
    $lineId,
    '-45.55',
    'USD',
    'Employee declined dental benefit; reversing deduction',
    $specialist,
));
$step('Step 3  Adjustment #1  -$45.55');

$recalculate(new RecalculateEarningLine($lineId, '1075.00', 'USD'));
$step('Step 4  System recalculates (ignored, line is frozen)');

$adjust(new AddManualAdjustment(
    $lineId,
    '100.10',
    'USD',
    'Late correction: missed approved overtime bonus',
    $specialist,
));
$step('Step 5  Adjustment #2  +$100.10');

$adjust(new AddManualAdjustment($lineId, '-0.10', 'USD', 'Minor rounding adjustment', $specialist));
$step('Step 6  Adjustment #3  -$0.10');

$adjust(new AddManualAdjustment($lineId, '-0.20', 'USD', 'Second minor rounding adjustment', $specialist));
$step('Step 7  Adjustment #4  -$0.20');

$adjust(new AddManualAdjustment(
    $lineId,
    '0.20',
    'USD',
    'Correcting mistake in adjustment #4',
    $specialist,
    4,
));
$step('Step 8  Adjustment #5  +$0.20  (compensates adjustment #4, made at step 7)');

echo "\nAudit history\n\n";
echo $table->render($audit(new GetEarningLineAudit($lineId)));
