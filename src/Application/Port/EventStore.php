<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Port;

use Alcor\Payroll\Domain\Shared\DomainEvent;

/**
 * Append-only. There is deliberately no update, no delete and no way to
 * address a single event, because the business rule is that nothing that
 * was recorded may ever change.
 */
interface EventStore
{
    /**
     * @param list<DomainEvent> $events
     *
     * @throws ConcurrencyConflict when the stream has moved since it was read
     */
    public function append(string $streamId, int $expectedVersion, array $events): void;

    /** @return list<DomainEvent> */
    public function load(string $streamId): array;
}
