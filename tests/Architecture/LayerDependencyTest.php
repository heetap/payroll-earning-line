<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Plain PHPUnit rather than an architecture package: the rule is one regular
 * expression, and a dependency to state a dependency rule would be ironic.
 */
final class LayerDependencyTest extends TestCase
{
    public function test_the_domain_knows_nothing_of_the_layers_around_it(): void
    {
        self::assertSame([], self::referencesFrom('src/Domain', ['Application', 'Infrastructure']));
    }

    public function test_the_application_does_not_reach_outwards_into_infrastructure(): void
    {
        self::assertSame([], self::referencesFrom('src/Application', ['Infrastructure']));
    }

    public function test_the_scan_actually_finds_something_when_a_layer_is_referenced(): void
    {
        self::assertNotSame(
            [],
            self::referencesFrom('src/Infrastructure', ['Domain']),
            'infrastructure legitimately uses the domain, so an empty result would mean the scan is broken',
        );
    }

    /**
     * @param list<string> $layers
     *
     * @return list<string>
     */
    private static function referencesFrom(string $directory, array $layers): array
    {
        $pattern = sprintf('/Alcor\\\\Payroll\\\\(%s)\\\\/', implode('|', $layers));
        $root = dirname(__DIR__, 2);
        $violations = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                self::fail(sprintf('Could not read %s', $file->getPathname()));
            }

            if (preg_match_all($pattern, $source, $matches) > 0) {
                foreach ($matches[1] as $layer) {
                    $violations[] = sprintf(
                        '%s references %s',
                        substr($file->getPathname(), strlen($root) + 1),
                        $layer,
                    );
                }
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }
}
