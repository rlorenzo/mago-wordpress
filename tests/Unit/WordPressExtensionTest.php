<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use Mago\Sdk\Extension;
use Mago\Sdk\Internal\SignalCancellationToken;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Settings;
use Rlorenzo\MagoWordPress\WordPressExtension;

use function array_map;
use function count;
use function file;
use function file_get_contents;
use function sort;
use function str_contains;
use function strlen;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

final class WordPressExtensionTest extends TestCase
{
    public function testFactoryOwnsStableRegistration(): void
    {
        $extension = WordPressExtension::create();

        self::assertSame('rlorenzo/mago-wordpress', $extension->identifier);
        self::assertSame('WordPress', $extension->name);
        self::assertSame('1.0.1', $extension->version);
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
     * Rules report through their Report, which honours phpcs suppression comments.
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

    /**
     * Every rule reports through its extension's Report, which keeps the
     * `honor-phpcs-comments` setting that extension was created with,
     * whatever another extension in the process was given.
     */
    public function testExtensionsKeepTheirOwnSettings(): void
    {
        $ignoring = WordPressExtension::create(new Settings(honorPhpcsComments: false));
        $honoring = WordPressExtension::create(new Settings(honorPhpcsComments: true));

        self::assertSame(count($ignoring->linterRules), self::reportsOnAnIgnoredLine($ignoring));
        self::assertSame(0, self::reportsOnAnIgnoredLine($honoring));
    }

    private static function reportsOnAnIgnoredLine(Extension $extension): int
    {
        $source = "<?php\n// phpcs:ignore\nf();\n";
        $file = new SourceFile(
            PHPVersion::fromParts(major: 8, minor: 1),
            'fixture.php',
            $source,
            [],
            new NodeStore([], records: '', nodeCount: 0),
            new ResolvedNameStore(starts: '', records: '', bytes: '', nameCount: 0),
            new TriviaStore(records: '', triviaCount: 0),
            null,
        );
        $context = new LintContext(
            $file,
            new Node(id: 0, kind: NodeKind::Program, span: new Span(start: 0, end: strlen($source)), parentId: null),
            new SignalCancellationToken(),
        );

        foreach ($extension->linterRules as $rule) {
            /** @var Report $report */
            $report = (new ReflectionProperty($rule, 'report'))->getValue($rule);
            $report->issue(
                $context,
                Issue::new('Report', new Span(start: 22, end: 26)),
                ['WordPress.Security.SafeRedirect'],
            );
        }

        return count($context->issues);
    }
}
