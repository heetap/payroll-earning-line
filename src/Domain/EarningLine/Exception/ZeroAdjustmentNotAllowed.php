<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;

// The leading backslash is deliberate, not a typo: `\DomainException` is the
// SPL base class being extended; `DomainException` is this project's marker
// interface being implemented. The backslash is what tells them apart.
final class ZeroAdjustmentNotAllowed extends \DomainException implements DomainException {}
