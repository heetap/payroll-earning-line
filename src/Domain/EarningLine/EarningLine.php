<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Alcor\Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Alcor\Payroll\Domain\Shared\Currency;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Alcor\Payroll\Domain\Shared\Exception\CurrencyMismatch;
use Alcor\Payroll\Domain\Shared\Money;
use DateTimeImmutable;
use LogicException;
use NoDiscard;

final class EarningLine
{
    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    private Currency $currency;

    private Money $systemValue;

    private Money $currentValue;

    /** The version this instance was loaded at; pending events do not advance it. */
    private int $version = 0;

    private function __construct(private readonly EarningLineId $id) {}

    public static function calculate(EarningLineId $id, Money $systemValue, DateTimeImmutable $at): self
    {
        $line = new self($id);
        $line->record(new EarningLineCalculated($id, $systemValue, $at));

        return $line;
    }

    /** @param iterable<DomainEvent> $events */
    public static function reconstitute(EarningLineId $id, iterable $events): self
    {
        $line = new self($id);

        foreach ($events as $event) {
            $line->apply($event);
            ++$line->version;
        }

        return $line;
    }

    public function recalculate(Money $newSystemValue, DateTimeImmutable $at): void
    {
        $this->assertSameCurrency($newSystemValue);

        if ($newSystemValue->equals($this->systemValue)) {
            return;
        }

        $this->record(new EarningLineRecalculated($this->id, $newSystemValue, $at));
    }

    public function id(): EarningLineId
    {
        return $this->id;
    }

    public function currentValue(): Money
    {
        return $this->currentValue;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return list<DomainEvent> */
    #[NoDiscard]
    public function pullRecordedEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }

    private function record(DomainEvent $event): void
    {
        $this->apply($event);
        $this->pendingEvents[] = $event;
    }

    /** The only place state changes, so a replayed line cannot drift from a live one. */
    private function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof EarningLineCalculated => $this->applyCalculated($event),
            $event instanceof EarningLineRecalculated => $this->applyRecalculated($event),
            default => throw new LogicException(sprintf('Unhandled event %s.', $event::class)),
        };
    }

    private function applyCalculated(EarningLineCalculated $event): void
    {
        $this->currency = $event->systemValue->currency;
        $this->systemValue = $event->systemValue;
        $this->currentValue = $event->systemValue;
    }

    private function applyRecalculated(EarningLineRecalculated $event): void
    {
        $this->systemValue = $event->newSystemValue;
        $this->currentValue = $event->newSystemValue;
    }

    private function assertSameCurrency(Money $amount): void
    {
        if ($amount->currency !== $this->currency) {
            throw CurrencyMismatch::between($this->currency, $amount->currency);
        }
    }
}
