<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class UnsupportedCurrency extends InvalidArgumentException implements DomainException {}
