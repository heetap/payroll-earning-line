<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Persistence;

use Alcor\Payroll\Application\Port\ConcurrencyConflict;
use Alcor\Payroll\Application\Port\EventStore;
use Alcor\Payroll\Domain\Shared\DomainEvent;
use Override;

final class InMemoryEventStore implements EventStore
{
    /** @var array<string, list<DomainEvent>> */
    private array $streams = [];

    #[Override]
    public function append(string $streamId, int $expectedVersion, array $events): void
    {
        $stream = $this->streams[$streamId] ?? [];

        if (count($stream) !== $expectedVersion) {
            throw new ConcurrencyConflict(sprintf(
                'Stream %s is at version %d, expected %d.',
                $streamId,
                count($stream),
                $expectedVersion,
            ));
        }

        $this->streams[$streamId] = [...$stream, ...$events];
    }

    #[Override]
    public function load(string $streamId): array
    {
        return $this->streams[$streamId] ?? [];
    }
}
