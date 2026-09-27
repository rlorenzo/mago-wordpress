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
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function array_map;
use function implode;
use function in_array;
use function ltrim;
use function preg_quote;
use function str_contains;
use function strtolower;
use function version_compare;

/**
 * Ports `WordPress.WP.DeprecatedClasses`.
 *
 * Flags an instantiation, a static method call, a class constant access, an
 * `extends` clause, and an `instanceof` check that reference a deprecated
 * WordPress core class. The `minimum-wp-version` setting restricts reports
 * to classes already deprecated in the project's oldest supported version.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class WpDeprecatedClassesRule implements Rule
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

    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/wp-deprecated-classes',
            name: 'WordPress deprecated classes',
            description: 'Reports instantiations, static calls, class constant accesses, extends clauses, and instanceof checks that reference a deprecated WordPress core class.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [
                NodeKind::Instantiation,
                NodeKind::StaticMethodCall,
                NodeKind::ClassConstantAccess,
                NodeKind::Extends,
                NodeKind::Binary,
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
            NodeKind::StaticMethodCall, NodeKind::ClassConstantAccess => self::single(self::classExpression(
                $file,
                $file->getChildren($node)[0] ?? null,
            )),
            NodeKind::Extends => self::extendsIdentifiers($file, $node),
            NodeKind::Binary => self::instanceofRhs($file, $node),
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
     * any other expression shape (a variable class, an anonymous class...).
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
    private static function extendsIdentifiers(SourceFile $file, Node $node): array
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

    /**
     * @return list<Node>
     */
    private static function instanceofRhs(SourceFile $file, Node $node): array
    {
        $children = $file->getChildren($node);
        $operator = $children[1] ?? null;
        if ($operator === null || strtolower($file->getText($operator)) !== 'instanceof') {
            return [];
        }

        return self::single(self::classExpression($file, $children[2] ?? null));
    }

    private function checkIdentifier(LintContext $context, Node $identifier): void
    {
        $file = $context->file;

        // The resolved name is fully qualified: a class inside a namespace
        // resolves to `Some\Namespace\ClassName`, which never matches the
        // global WordPress class names in the lookup table.
        $resolved = $file->getResolvedName($identifier)?->name;
        if ($resolved === null) {
            return;
        }

        $normalized = ltrim($resolved, characters: '\\');
        if (str_contains($normalized, '\\')) {
            return;
        }

        $since = Lists::DEPRECATED_CLASSES[strtolower($normalized)] ?? null;
        if ($since === null || !$this->isReportable($since)) {
            return;
        }

        $name = $file->getText($identifier);
        $context->report(Issue::new(
            "Class `{$name}` has been deprecated since WordPress {$since}.",
            $identifier->span,
        )->withNote('Deprecated classes may be removed in a future WordPress release.'));
    }

    /**
     * Whether a class deprecated since $deprecatedSince should be reported
     * under the project's configured minimum WordPress version.
     *
     * An empty or unparsable `minimum-wp-version` reports every class in
     * the table, matching the ported sniff.
     */
    private function isReportable(string $deprecatedSince): bool
    {
        $minimum = $this->settings->normalizedMinimumWpVersion();

        return $minimum === null || version_compare($deprecatedSince, $minimum, operator: '<=');
    }

    /**
     * Builds the file gate from the table's class names.
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
        ), array_keys(Lists::DEPRECATED_CLASSES)));

        return new FileGate(pattern: "/(?<!\\w)(?:{$alternation})(?!\\w)/i");
    }
}
