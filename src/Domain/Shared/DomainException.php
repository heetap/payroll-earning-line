<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared;

use Throwable;

/**
 * Marker for every error the domain raises on purpose, so an application
 * boundary can tell a rule violation from a programming mistake.
 */
interface DomainException extends Throwable {}
