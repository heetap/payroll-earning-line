<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Identity;

/**
 * Identity generation is infrastructure: the domain validates a UUID but
 * never makes one, which keeps randomness out of the model. Used only by
 * bin/scenario.php, which stands in for the caller that would supply an id.
 */
final class UuidV4
{
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
