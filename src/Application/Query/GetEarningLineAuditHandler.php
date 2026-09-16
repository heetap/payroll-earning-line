<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

use Alcor\Payroll\Application\Port\EventStore;
use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;

/**
 * Reads the raw stream instead of loading the aggregate: the audit needs
 * the comments, authors and refused recalculations that the write model
 * deliberately does not keep.
 */
final class GetEarningLineAuditHandler
{
    public function __construct(
        private readonly EventStore $events,
        private readonly AuditHistoryProjection $projection,
    ) {}

    public function __invoke(GetEarningLineAudit $query): AuditHistoryView
    {
        $id = new EarningLineId($query->lineId);
        $stream = $this->events->load($id->value);

        if ($stream === []) {
            throw EarningLineNotFound::withId($id);
        }

        return $this->projection->project($id, $stream);
    }
}
