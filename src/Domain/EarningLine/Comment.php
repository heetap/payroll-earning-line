<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Exception\InvalidComment;

final readonly class Comment
{
    public const int MAX_LENGTH = 500;

    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidComment::blank();
        }

        $length = mb_strlen($trimmed);

        if ($length > self::MAX_LENGTH) {
            throw InvalidComment::tooLong($length, self::MAX_LENGTH);
        }

        $this->value = $trimmed;
    }
}
