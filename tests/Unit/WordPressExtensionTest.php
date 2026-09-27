<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use Mago\Sdk\Linter\Rule;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Rlorenzo\MagoWordPress\WordPressExtension;

use function array_map;
use function file;
use function file_get_contents;
use function sort;
use function str_contains;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

final class WordPressExtensionTest extends TestCase
{
    public function testFactoryOwnsStableRegistration(): void
    {
        $extension = WordPressExtension::create();

        self::assertSame('rlorenzo/mago-wordpress', $extension->identifier);
        self::assertSame('WordPress', $extension->name);
        self::assertSame('0.2.0', $extension->version);
        self::assertCount(0, $extension->analyzerPlugins);
        self::assertNull($extension->workerReducer);
    }

    public function testRegisteredRulesMatchThePinnedList(): void
    {
        $registered = array_map(
            static fn(Rule $rule): string => $rule->getDefinition()->code,
            WordPressExtension::create()->linterRules,
        );
        $expected = file(__DIR__ . '/../corpus/expected-rules.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($expected);

        sort($registered);
        sort($expected);

        self::assertSame($expected, $registered);
    }

    /**
     * Rules report through Report::issue(), which honours phpcs suppression comments.
     */
    public function testRulesDoNotReportDirectly(): void
    {
        foreach (WordPressExtension::create()->linterRules as $rule) {
            for ($class = new ReflectionClass($rule); $class !== false; $class = $class->getParentClass()) {
                $source = (string) file_get_contents((string) $class->getFileName());
                self::assertFalse(
                    str_contains($source, '$context->report('),
                    "{$class->getName()} calls report() directly",
                );
            }
        }
    }
}
