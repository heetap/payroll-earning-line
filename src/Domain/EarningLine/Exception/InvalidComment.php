<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine\Exception;

use Alcor\Payroll\Domain\Shared\DomainException;
use InvalidArgumentException;

final class InvalidComment extends InvalidArgumentException implements DomainException
{
    public static function blank(): self
    {
        return new self('An adjustment must explain itself: the comment is mandatory.');
    }

    public static function tooLong(int $length, int $limit): self
    {
        return new self(sprintf('A comment may be at most %d characters, got %d.', $limit, $length));
    }
}
