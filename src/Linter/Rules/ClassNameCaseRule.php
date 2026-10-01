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
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function array_combine;
use function array_map;
use function array_pop;
use function explode;
use function ltrim;
use function strtolower;

/**
 * Ports `WordPress.WP.ClassNameCase`.
 *
 * The sniff's `$wp_classes` group lives in `CoreClasses`; its other groups
 * (default themes and bundled libraries such as getID3, PHPMailer, Requests,
 * SimplePie, Avifinfo and the AI Client) in
 * `Lists::CLASS_NAME_CASE_BUNDLED_CLASSES`, keyed by group so the sniff's `exclude`
 * property can drop one.
 *
 * The class name is resolved with `SourceFile::getResolvedName()`, which
 * plays the part of the sniff's own `get_namespaced_classname()`: a bare,
 * qualified or `namespace\`-relative name qualifies against the file's
 * namespace and `use` imports, and the fully qualified result is compared
 * case-insensitively. `self`/`parent`/`static` arrive as a `Keyword` node,
 * never an identifier, so they are skipped without special-casing.
 */
final class ClassNameCaseRule implements Rule
{
    private const SNIFF = 'WordPress.WP.ClassNameCase';

    private ?FileGate $gate = null;

    /** @var array<string, string>|null lowercase name => properly cased name */
    private ?array $properCase = null;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/class-name-case',
            name: 'WordPress class name case',
            description: 'Reports an instantiation, static call, class constant access, extends clause, or implements clause that references a WordPress core or bundled-library class with the wrong case.',
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
        // Every match puts a listed class's last name segment directly in the source.
        $this->gate ??= FileGate::forWords(array_map(static function (string $name): string {
            $segments = explode(separator: '\\', string: $name);

            return array_pop($segments);
        }, $this->names()));
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
        $resolved = $context->file->getResolvedName($identifier)?->name;
        if ($resolved === null) {
            return;
        }

        $name = ltrim($resolved, characters: '\\');

        $properCase = $this->properCaseMap()[strtolower($name)] ?? null;
        if ($properCase === null || $properCase === $name) {
            // Not a listed class, or already using the proper case.
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                "References the WordPress class `{$name}` with the wrong case; expected `{$properCase}`.",
                $identifier->span,
            )->withHelp("Use the properly cased name: `{$properCase}`."),
            [self::SNIFF . '.Incorrect'],
        );
    }

    /**
     * The listed classes, less any group the sniff's `exclude` property drops.
     *
     * @return list<string>
     */
    private function names(): array
    {
        $groups = ['wp_classes' => CoreClasses::NAMES, ...Lists::CLASS_NAME_CASE_BUNDLED_CLASSES];
        $names = [];
        foreach ($groups as $group => $classes) {
            if ($this->report->excludesGroup(self::SNIFF, $group)) {
                continue;
            }

            $names = [...$names, ...$classes];
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function properCaseMap(): array
    {
        return $this->properCase ??= array_combine(array_map(strtolower(...), $this->names()), $this->names());
    }
}
