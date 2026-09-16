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
            throw new InvalidComment('An adjustment must explain itself: the comment is mandatory.');
        }

        $length = mb_strlen($trimmed);

        if ($length > self::MAX_LENGTH) {
            throw new InvalidComment(sprintf('A comment may be at most %d characters, got %d.', self::MAX_LENGTH, $length));
        }

        $this->value = $trimmed;
    }
}
