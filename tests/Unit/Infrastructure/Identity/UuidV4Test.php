<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Infrastructure\Identity;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Infrastructure\Identity\UuidV4;
use PHPUnit\Framework\TestCase;

final class UuidV4Test extends TestCase
{
    public function test_it_generates_an_identifier_the_domain_accepts(): void
    {
        $uuid = UuidV4::generate();

        self::assertSame($uuid, new EarningLineId($uuid)->value);
    }

    public function test_two_generated_identifiers_differ(): void
    {
        self::assertNotSame(UuidV4::generate(), UuidV4::generate());
    }
}
