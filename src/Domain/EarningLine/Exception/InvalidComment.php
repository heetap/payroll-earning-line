<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidComment extends InvalidArgumentException implements DomainException {}
