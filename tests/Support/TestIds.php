<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Support;

/**
 * Fixed identities: the domain never generates a UUID, so tests supply one
 * and stay deterministic.
 */
final class TestIds
{
    public const string LINE = '018f3a2c-5b7e-4d9a-9c1f-2e6b8a4d0f31';
    public const string LINE_OTHER = '018f3a2c-5b7e-4d9a-9c1f-2e6b8a4d0f32';
    public const string SPECIALIST = 'specialist-alice';
}
