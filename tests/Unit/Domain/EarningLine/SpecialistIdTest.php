<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Exception\InvalidSpecialistId;
use Alcor\Payroll\Domain\EarningLine\SpecialistId;
use PHPUnit\Framework\TestCase;

final class SpecialistIdTest extends TestCase
{
    public function test_it_is_an_opaque_identifier_from_another_context(): void
    {
        self::assertSame('okta|00u1a2b3c4', new SpecialistId('okta|00u1a2b3c4')->value);
    }

    public function test_it_trims_surrounding_whitespace(): void
    {
        self::assertSame('alice', new SpecialistId(' alice ')->value);
    }

    public function test_an_adjustment_must_name_who_made_it(): void
    {
        $this->expectException(InvalidSpecialistId::class);

        new SpecialistId('   ');
    }

    public function test_it_rejects_an_identifier_one_character_too_long(): void
    {
        $this->expectException(InvalidSpecialistId::class);

        new SpecialistId(str_repeat('a', SpecialistId::MAX_LENGTH + 1));
    }

    public function test_equality_compares_the_identifier(): void
    {
        self::assertTrue(new SpecialistId('alice')->equals(new SpecialistId('alice')));
        self::assertFalse(new SpecialistId('alice')->equals(new SpecialistId('bob')));
    }
}
