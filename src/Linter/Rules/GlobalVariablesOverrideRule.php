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
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function in_array;
use function ltrim;

use const PHP_INT_MAX;

/**
 * Ports `WordPress.WP.GlobalVariablesOverride`.
 *
 * `Program` is a target for one reason: every node then has its parent chain
 * in the snapshot, whatever other rules are active, so the ancestor walks
 * below (scope detection, `global`-import lookup) work for every kind
 * without declaring each of them as a target too.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class GlobalVariablesOverrideRule implements Rule
{
    /**
     * Function-like scopes. An assignment outside all of these is top-level.
     */
    private const SCOPE_KINDS = [
        NodeKind::Function,
        NodeKind::Method,
        NodeKind::Closure,
        NodeKind::ArrowFunction,
        NodeKind::PropertyHook,
    ];

    /**
     * Globals themes and plugins are expected to set (WPCS `$override_allowed`).
     */
    private const OVERRIDE_ALLOWED = ['content_width', 'wp_cockneyreplace'];

    /**
     * Globals WordPress core sets that WPCS's generated list lacks.
     */
    private const EXTRA_GLOBALS = ['query_string'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/global-variables-override',
            name: 'Global variables override',
            description: 'Reports assignments that overwrite WordPress-protected global variables such as $post, $wp_query, or $wpdb. An assignment is flagged in the top-level scope, or inside a function-like scope where the variable was imported with a global statement. Writes to $GLOBALS[...] with a protected key are flagged anywhere.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        /** @var array<int, array<string, int>> $importsByScope scope node id => variable name => earliest global-statement start offset */
        $importsByScope = [];
        foreach ($file->getDescendants($context->node, NodeKind::Global) as $global) {
            $scope = $this->nearestScope($file, $global);
            if ($scope === null) {
                continue;
            }

            foreach ($file->getDescendants($global, NodeKind::DirectVariable) as $variable) {
                // Descendants arrive in source order, so the first import is the earliest.
                $importsByScope[$scope->id][$file->getText($variable)] ??= $global->span->start;
            }
        }

        foreach ($file->getDescendants($context->node, NodeKind::Assignment) as $assignment) {
            $this->checkAssignment($context, $file, $assignment, $importsByScope);
        }
    }

    /**
     * @param array<int, array<string, int>> $importsByScope
     */
    private function checkAssignment(
        LintContext $context,
        SourceFile $file,
        Node $assignment,
        array $importsByScope,
    ): void {
        $lhsChild = $file->getChildren($assignment)[0] ?? $assignment;
        $lhs = Values::unwrap($file, $lhsChild);

        if ($lhs->kind === NodeKind::Variable) {
            $variable = $file->getChildren($lhs)[0] ?? $lhs;
            if ($variable->kind !== NodeKind::DirectVariable) {
                return;
            }

            $this->checkDirectAssignment($context, $file, $lhsChild, $variable, $importsByScope);
            return;
        }

        if ($lhs->kind === NodeKind::ArrayAccess) {
            $this->checkGlobalsWrite($context, $file, $lhsChild, $lhs);
        }
    }

    /**
     * @param array<int, array<string, int>> $importsByScope
     */
    private function checkDirectAssignment(
        LintContext $context,
        SourceFile $file,
        Node $lhsSpanNode,
        Node $variable,
        array $importsByScope,
    ): void {
        $text = $file->getText($variable);
        $name = self::protectedGlobal($text);
        if ($name === null) {
            return;
        }

        $scope = $this->nearestScope($file, $variable);
        if ($scope !== null && ($importsByScope[$scope->id][$text] ?? PHP_INT_MAX) >= $variable->span->start) {
            return;
        }

        $this->report($context, $lhsSpanNode, $name);
    }

    private function checkGlobalsWrite(
        LintContext $context,
        SourceFile $file,
        Node $lhsSpanNode,
        Node $arrayAccess,
    ): void {
        $children = $file->getChildren($arrayAccess);

        $base = Values::unwrap($file, $children[0] ?? $arrayAccess);
        if ($base->kind === NodeKind::Variable) {
            $base = $file->getChildren($base)[0] ?? $base;
        }

        if ($base->kind !== NodeKind::DirectVariable || $file->getText($base) !== '$GLOBALS') {
            return;
        }

        $key = Values::unwrap($file, $children[1] ?? $arrayAccess);
        $keyValue = Values::literalString($file, $key);
        if ($keyValue === null) {
            return;
        }

        $name = self::protectedGlobal($keyValue);
        if ($name === null) {
            return;
        }

        $this->report($context, $lhsSpanNode, $name);
    }

    private function report(LintContext $context, Node $lhsSpanNode, string $name): void
    {
        $context->report(Issue::new(
            "Assignment overwrites the WordPress global variable \${$name}.",
            $lhsSpanNode->span,
            "\${$name} is a WordPress global and must not be overwritten",
        )->withNote(
            'WordPress core and other plugins rely on this global; overwriting it can break them in unpredictable ways.',
        )->withHelp('Use a differently named local variable, or the appropriate WordPress API instead.'));
    }

    /**
     * Returns the nearest enclosing function-like scope, or null in the top-level scope.
     */
    private function nearestScope(SourceFile $file, Node $node): ?Node
    {
        $parent = $file->getParent($node);
        while ($parent !== null) {
            if (in_array($parent->kind, self::SCOPE_KINDS, strict: true)) {
                return $parent;
            }

            $parent = $file->getParent($parent);
        }

        return null;
    }

    /**
     * Accepts a variable name (`$post`) or a `$GLOBALS` key, with or without the `$` prefix.
     */
    private static function protectedGlobal(string $name): ?string
    {
        $bare = ltrim($name, characters: '$');

        if (in_array($bare, self::OVERRIDE_ALLOWED, strict: true)) {
            return null;
        }

        return in_array($bare, Lists::WP_GLOBAL_VARIABLES, strict: true)
        || in_array($bare, self::EXTRA_GLOBALS, strict: true)
            ? $bare
            : null;
    }
}
