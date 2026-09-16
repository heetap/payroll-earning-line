<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Persistence;

use Alcor\Payroll\Application\Port\EventStore;
use Alcor\Payroll\Domain\EarningLine\EarningLine;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\EarningLineRepository;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Override;

final class EventSourcedEarningLineRepository implements EarningLineRepository
{
    public function __construct(private readonly EventStore $events) {}

    #[Override]
    public function exists(EarningLineId $id): bool
    {
        return $this->events->load($id->value) !== [];
    }

    #[Override]
    public function get(EarningLineId $id): EarningLine
    {
        $stream = $this->events->load($id->value);

        // Guarded here so the aggregate never has to defend against a stream
        // shape the adapter already refuses to hand it.
        if ($stream === []) {
            throw EarningLineNotFound::withId($id);
        }

        return EarningLine::reconstitute($id, $stream);
    }

    #[Override]
    public function save(EarningLine $line): void
    {
        // Drained before the append, so a failed append leaves $line with no
        // pending events; retrying on this same instance would write nothing.
        $events = $line->pullRecordedEvents();

        if ($events === []) {
            return;
        }

        $this->events->append($line->id()->value, $line->version(), $events);
    }
}
