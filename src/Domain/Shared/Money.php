<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Shared;

use Alcor\Payroll\Domain\Shared\Exception\CurrencyMismatch;
use Alcor\Payroll\Domain\Shared\Exception\InvalidMoneyAmount;
use NoDiscard;

final readonly class Money
{
    private function __construct(
        public int $minor,
        public Currency $currency,
    ) {}

    public static function ofMinor(int $minor, Currency $currency): self
    {
        return new self($minor, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromDecimal(string $amount, Currency $currency): self
    {
        $scale = $currency->minorUnits();
        $fraction = $scale > 0 ? sprintf('(?:\.(?<fraction>\d{1,%d}))?', $scale) : '';

        if (preg_match('/\A(?<sign>[+-])?(?<units>\d+)' . $fraction . '\z/', $amount, $matches) !== 1) {
            throw new InvalidMoneyAmount(sprintf(
                'Expected a decimal amount with at most %d decimal places, got "%s".',
                $currency->minorUnits(),
                $amount,
            ));
        }

        $digits = ltrim($matches['units'] . str_pad($matches['fraction'] ?? '', $scale, '0'), '0');

        if ($digits === '') {
            return new self(0, $currency);
        }

        if (self::exceedsIntegerRange($digits)) {
            throw new InvalidMoneyAmount(sprintf('Amount "%s" does not fit in the supported range.', $amount));
        }

        $minor = (int) $digits;

        return new self(($matches['sign'] ?? '') === '-' ? -$minor : $minor, $currency);
    }

    #[NoDiscard]
    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }

        // Checked before adding: an overflowing + yields a float in PHP, and
        // floats are banned here precisely because they lose cents silently.
        if ($other->minor > 0 && $this->minor > \PHP_INT_MAX - $other->minor) {
            throw InvalidMoneyAmount::overflow();
        }

        if ($other->minor < 0 && $this->minor < \PHP_INT_MIN - $other->minor) {
            throw InvalidMoneyAmount::overflow();
        }

        return new self($this->minor + $other->minor, $this->currency);
    }

    #[NoDiscard]
    public function negate(): self
    {
        if ($this->minor === \PHP_INT_MIN) {
            throw InvalidMoneyAmount::overflow();
        }

        return new self(-$this->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /**
     * Compares digit strings rather than casting, because casting an oversized
     * numeric string silently yields PHP_INT_MAX.
     */
    private static function exceedsIntegerRange(string $digits): bool
    {
        $max = (string) \PHP_INT_MAX;

        return strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0);
    }
}
