<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Exception\InvalidEarningLineId;

final readonly class EarningLineId
{
    /** Versions 1-8 and the RFC 4122 variant; the nil UUID is not an identity. */
    private const string PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    public string $value;

    public function __construct(string $value)
    {
        $normalised = strtolower(trim($value));

        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidEarningLineId::notAUuid($value);
        }

        $this->value = $normalised;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
