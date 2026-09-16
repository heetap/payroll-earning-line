<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Port;

use Alcor\Payroll\Domain\Shared\DomainException;
use RuntimeException;

/**
 * Raised only when two writers race on the same stream. A duplicate
 * creation is EarningLineAlreadyExists, so this exception always means
 * "reload and retry".
 */
final class ConcurrencyConflict extends RuntimeException implements DomainException {}
