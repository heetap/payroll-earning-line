<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;

final class UnknownAdjustment extends \DomainException implements DomainException {}
