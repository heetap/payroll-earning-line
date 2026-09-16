<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Exception\InvalidSpecialistId;

/**
 * Opaque: the identity of a specialist belongs to another bounded context,
 * so this asserts presence and a sane length, and assumes no format.
 */
final readonly class SpecialistId
{
    public const int MAX_LENGTH = 100;

    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidSpecialistId('An adjustment must record who made it.');
        }

        $length = mb_strlen($trimmed);

        if ($length > self::MAX_LENGTH) {
            throw new InvalidSpecialistId(sprintf('A specialist id may be at most %d characters, got %d.', self::MAX_LENGTH, $length));
        }

        $this->value = $trimmed;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
