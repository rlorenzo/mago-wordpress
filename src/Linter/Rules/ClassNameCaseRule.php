<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\ClassReferences;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\CoreClasses;

use function array_combine;
use function array_map;
use function strtolower;

/**
 * Ports `WordPress.WP.ClassNameCase`.
 *
 * Only the sniff's `$wp_classes` group is ported (see `CoreClasses`); the
 * bundled-library groups (`wp_themes_classes`, `aiclient_classes`,
 * `avif_classes`, `getid3_classes`, `phpmailer_classes`, `requests_classes`,
 * `simplepie_classes`) are out of scope.
 *
 * The class name is resolved with `SourceFile::getResolvedName()`, which
 * plays the part of the sniff's own `get_namespaced_classname()`: a bare
 * name qualifies against the file's namespace and `use` imports, so it only
 * matches a WP core class when it actually resolves to the global
 * namespace. `self`/`parent`/`static` arrive as a `Keyword` node, never an
 * identifier, so they are skipped without special-casing.
 */
final class ClassNameCaseRule implements Rule
{
    private const SNIFF = 'WordPress.WP.ClassNameCase';

    private ?FileGate $gate = null;

    /** @var array<string, string>|null lowercase name => properly cased name */
    private static ?array $properCase = null;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/class-name-case',
            name: 'WordPress class name case',
            description: 'Reports an instantiation, static call, class constant access, extends clause, or implements clause that references a WordPress core class with the wrong case.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [
                NodeKind::Instantiation,
                NodeKind::StaticMethodCall,
                NodeKind::StaticPropertyAccess,
                NodeKind::ClassConstantAccess,
                NodeKind::Extends,
                NodeKind::Implements,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        // Every match puts a core class name directly in the source.
        $this->gate ??= FileGate::forWords(CoreClasses::NAMES);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($this->candidates($context->file, $context->node) as $identifier) {
            $this->checkIdentifier($context, $identifier);
        }
    }

    /**
     * Returns the class-name identifier nodes a target node references.
     *
     * @return list<Node>
     */
    private function candidates(SourceFile $file, Node $node): array
    {
        return match ($node->kind) {
            NodeKind::Instantiation => ClassReferences::identifier($file, $file->getChildren($node)[1] ?? null),
            NodeKind::StaticMethodCall,
            NodeKind::StaticPropertyAccess,
            NodeKind::ClassConstantAccess,
                => ClassReferences::identifier($file, $file->getChildren($node)[0] ?? null),
            NodeKind::Extends, NodeKind::Implements => ClassReferences::heritage($file, $node),
            default => [],
        };
    }

    private function checkIdentifier(LintContext $context, Node $identifier): void
    {
        $name = ClassReferences::globalName($context->file, $identifier);
        if ($name === null) {
            return;
        }

        $properCase = self::properCaseMap()[strtolower($name)] ?? null;
        if ($properCase === null || $properCase === $name) {
            // Not a WP core class, or already using the proper case.
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                "References the WordPress core class `{$name}` with the wrong case; expected `{$properCase}`.",
                $identifier->span,
            )->withHelp("Use the properly cased name: `{$properCase}`."),
            [self::SNIFF . '.Incorrect'],
        );
    }

    /**
     * @return array<string, string>
     */
    private static function properCaseMap(): array
    {
        return self::$properCase ??= array_combine(array_map(strtolower(...), CoreClasses::NAMES), CoreClasses::NAMES);
    }
}
