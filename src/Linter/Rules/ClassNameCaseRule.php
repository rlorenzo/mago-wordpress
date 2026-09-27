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
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\CoreClasses;

use function array_combine;
use function array_map;
use function implode;
use function in_array;
use function ltrim;
use function preg_quote;
use function str_contains;
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
    /**
     * Node kinds a class name can arrive as, once unwrapped from its
     * `Expression` wrapper.
     */
    private const IDENTIFIER_KINDS = [
        NodeKind::Identifier,
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
    ];

    private ?FileGate $gate = null;

    /** @var array<string, string>|null lowercase name => properly cased name */
    private static ?array $properCase = null;

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
        $this->gate ??= self::buildGate();
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
            NodeKind::Instantiation => self::single(self::classExpression($file, $file->getChildren($node)[1] ?? null)),
            NodeKind::StaticMethodCall,
            NodeKind::StaticPropertyAccess,
            NodeKind::ClassConstantAccess,
                => self::single(self::classExpression($file, $file->getChildren($node)[0] ?? null)),
            NodeKind::Extends, NodeKind::Implements => self::heritageIdentifiers($file, $node),
            default => [],
        };
    }

    /**
     * @return list<Node>
     */
    private static function single(?Node $node): array
    {
        return $node === null ? [] : [$node];
    }

    /**
     * Unwraps an `Expression` down to a class-name identifier, or NULL for
     * any other expression shape (a variable class, `self`/`parent`/`static`,
     * an anonymous class...).
     */
    private static function classExpression(SourceFile $file, ?Node $node): ?Node
    {
        if ($node === null) {
            return null;
        }

        $node = Values::unwrap($file, $node);

        return in_array($node->kind, self::IDENTIFIER_KINDS, strict: true) ? $node : null;
    }

    /**
     * @return list<Node>
     */
    private static function heritageIdentifiers(SourceFile $file, Node $node): array
    {
        $identifiers = [];
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                continue;
            }

            $identifiers[] = $child;
        }

        return $identifiers;
    }

    private function checkIdentifier(LintContext $context, Node $identifier): void
    {
        $file = $context->file;

        // The resolved name is fully qualified: a class inside a namespace
        // resolves to `Some\Namespace\ClassName`, which never matches a
        // bare WordPress core class name.
        $resolved = $file->getResolvedName($identifier)?->name;
        if ($resolved === null) {
            return;
        }

        $normalized = ltrim($resolved, characters: '\\');
        if (str_contains($normalized, '\\')) {
            return;
        }

        $properCase = self::properCaseMap()[strtolower($normalized)] ?? null;
        if ($properCase === null || $properCase === $normalized) {
            // Not a WP core class, or already using the proper case.
            return;
        }

        $name = $file->getText($identifier);
        $context->report(Issue::new(
            "References the WordPress core class `{$normalized}` with the wrong case; expected `{$properCase}`.",
            $identifier->span,
        )->withHelp("Use the properly cased name: `{$properCase}`."));
    }

    /**
     * @return array<string, string>
     */
    private static function properCaseMap(): array
    {
        return self::$properCase ??= array_combine(array_map(strtolower(...), CoreClasses::NAMES), CoreClasses::NAMES);
    }

    /**
     * Builds the file gate from the core class list.
     *
     * Every match puts a wanted class name directly in the source, so a
     * text screen can skip the node walk for a file that mentions none of
     * them.
     */
    private static function buildGate(): FileGate
    {
        $alternation = implode('|', array_map(static fn(string $name): string => preg_quote(
            $name,
            delimiter: '/',
        ), CoreClasses::NAMES));

        return new FileGate(pattern: "/(?<!\\w)(?:{$alternation})(?!\\w)/i");
    }
}
