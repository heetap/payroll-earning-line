<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\EarningLineId;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidEarningLineId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EarningLineIdTest extends TestCase
{
    private const string VALID = '018f3a2c-5b7e-4d9a-9c1f-2e6b8a4d0f31';

    public function test_it_accepts_an_rfc_4122_uuid(): void
    {
        self::assertSame(self::VALID, new EarningLineId(self::VALID)->value);
    }

    public function test_it_normalises_case_so_two_spellings_are_one_identity(): void
    {
        $upper = new EarningLineId(strtoupper(self::VALID));

        self::assertSame(self::VALID, $upper->value);
        self::assertTrue($upper->equals(new EarningLineId(self::VALID)));
    }

    public function test_different_uuids_are_different_identities(): void
    {
        $other = new EarningLineId('018f3a2c-5b7e-4d9a-9c1f-2e6b8a4d0f32');

        self::assertFalse(new EarningLineId(self::VALID)->equals($other));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'not a uuid' => ['line-1'];
        yield 'missing a group' => ['018f3a2c-5b7e-4d9a-9c1f'];
        yield 'nil uuid has no version' => ['00000000-0000-0000-0000-000000000000'];
        yield 'bad variant' => ['018f3a2c-5b7e-4d9a-1c1f-2e6b8a4d0f31'];
        yield 'not hexadecimal' => ['018f3a2c-5b7e-4d9a-9c1f-2e6b8a4d0fzz'];
        yield 'braced' => ['{018f3a2c-5b7e-4d9a-9c1f-2e6b8a4d0f31}'];
    }

    #[DataProvider('invalidIds')]
    public function test_identity_must_be_a_uuid(string $value): void
    {
        $this->expectException(InvalidEarningLineId::class);

        new EarningLineId($value);
    }
}
