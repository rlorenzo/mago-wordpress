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
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function strtolower;
use function version_compare;

/**
 * Ports `WordPress.WP.DeprecatedClasses`.
 *
 * Flags an instantiation, a static method call, a class constant access, an
 * `extends` clause, and an `instanceof` check that reference a deprecated
 * WordPress core class. The `minimum-wp-version` setting restricts reports
 * to classes already deprecated in the project's oldest supported version.
 */
final class WpDeprecatedClassesRule implements Rule
{
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
        // Every match puts a deprecated class name directly in the source.
        $this->gate ??= FileGate::forWords(array_keys(Lists::DEPRECATED_CLASSES));
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
            NodeKind::StaticMethodCall, NodeKind::ClassConstantAccess => ClassReferences::identifier(
                $file,
                $file->getChildren($node)[0] ?? null,
            ),
            NodeKind::Extends => ClassReferences::heritage($file, $node),
            NodeKind::Binary => self::instanceofRhs($file, $node),
            default => [],
        };
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

        return ClassReferences::identifier($file, $children[2] ?? null);
    }

    private function checkIdentifier(LintContext $context, Node $identifier): void
    {
        $file = $context->file;

        $normalized = ClassReferences::globalName($file, $identifier);
        if ($normalized === null) {
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
}
