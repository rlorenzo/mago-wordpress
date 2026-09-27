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
use function substr;

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
            description: 'Reports writes that overwrite WordPress-protected global variables such as $post, $wp_query, or $wpdb. This covers direct assignments (including compound assignments), foreach key/value bindings, and list/array destructuring targets, however deeply nested. A write is flagged in the top-level scope, or inside a function-like scope where the variable was imported with a global statement. Writes to $GLOBALS[...] with a protected key are flagged anywhere; the key is compared verbatim, so $GLOBALS[\'$post\'] is a different key from $GLOBALS[\'post\'].',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        // One walk of the whole program; each pass below filters it by kind.
        $nodes = $file->getDescendants($context->node);

        /** @var array<int, array<string, int>> $importsByScope scope node id => variable name => earliest global-statement start offset */
        $importsByScope = [];
        foreach ($nodes as $global) {
            if ($global->kind !== NodeKind::Global) {
                continue;
            }

            $scope = $this->nearestScope($file, $global);
            if ($scope === null) {
                continue;
            }

            foreach ($file->getDescendants($global, NodeKind::DirectVariable) as $variable) {
                // Descendants arrive in source order, so the first import is the earliest.
                $importsByScope[$scope->id][$file->getText($variable)] ??= $global->span->start;
            }
        }

        foreach ($nodes as $node) {
            $targets = match ($node->kind) {
                // `$post = ...` and `foreach ($x as $post)`: the first child binds.
                NodeKind::Assignment, NodeKind::ForeachValueTarget => [$file->getChildren($node)[0] ?? $node],
                // `foreach ($x as $post => $item)`: both the key and the value bind.
                NodeKind::ForeachKeyValueTarget => $file->getChildren($node),
                default => [],
            };

            foreach ($targets as $target) {
                $this->checkTarget($context, $file, $target, $importsByScope);
            }
        }
    }

    /**
     * Checks a single write target: a plain variable, a `$GLOBALS[...]`
     * write, or a list/array destructuring pattern, which is walked
     * recursively since each of its elements is itself a write target.
     *
     * @param array<int, array<string, int>> $importsByScope
     */
    private function checkTarget(LintContext $context, SourceFile $file, Node $targetChild, array $importsByScope): void
    {
        $target = Values::unwrap($file, $targetChild);

        if ($target->kind === NodeKind::Variable) {
            $variable = $file->getChildren($target)[0] ?? $target;
            if ($variable->kind !== NodeKind::DirectVariable) {
                return;
            }

            $this->checkVariableTarget($context, $file, $target, $variable, $importsByScope);
            return;
        }

        if ($target->kind === NodeKind::ArrayAccess) {
            $this->checkGlobalsWrite($context, $file, $target);
            return;
        }

        if (in_array($target->kind, [NodeKind::Array, NodeKind::LegacyArray, NodeKind::List], strict: true)) {
            foreach ($file->getChildren($target) as $element) {
                $this->checkDestructuringElement($context, $file, $element, $importsByScope);
            }
        }
    }

    /**
     * @param array<int, array<string, int>> $importsByScope
     */
    private function checkDestructuringElement(
        LintContext $context,
        SourceFile $file,
        Node $element,
        array $importsByScope,
    ): void {
        $wrapped = $file->getChildren($element)[0] ?? null;
        if ($wrapped === null) {
            return;
        }

        // A key-value element's target is its value; the key is a plain
        // expression selecting the source offset, never a write target.
        $valueChild = match ($wrapped->kind) {
            NodeKind::KeyValueArrayElement => $file->getChildren($wrapped)[1] ?? null,
            NodeKind::ValueArrayElement => $file->getChildren($wrapped)[0] ?? null,
            default => null, // A missing (skipped) slot has nothing to check.
        };

        if ($valueChild !== null) {
            $this->checkTarget($context, $file, $valueChild, $importsByScope);
        }
    }

    /**
     * @param array<int, array<string, int>> $importsByScope
     */
    private function checkVariableTarget(
        LintContext $context,
        SourceFile $file,
        Node $spanNode,
        Node $variable,
        array $importsByScope,
    ): void {
        $text = $file->getText($variable);
        // A DirectVariable's text is always the sigil plus the name (e.g. `$post`);
        // strip exactly that one leading `$`, never more.
        $name = self::protectedGlobal(substr($text, offset: 1));
        if ($name === null) {
            return;
        }

        $scope = $this->nearestScope($file, $variable);
        if ($scope !== null && ($importsByScope[$scope->id][$text] ?? PHP_INT_MAX) >= $variable->span->start) {
            return;
        }

        $this->report($context, $spanNode, $name);
    }

    private function checkGlobalsWrite(LintContext $context, SourceFile $file, Node $arrayAccess): void
    {
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

        $this->report($context, $arrayAccess, $name);
    }

    private function report(LintContext $context, Node $spanNode, string $name): void
    {
        $context->report(Issue::new(
            "Assignment overwrites the WordPress global variable \${$name}.",
            $spanNode->span,
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
     * Accepts a bare name, already stripped of any variable sigil by the
     * caller: a variable's name (`post`) or a `$GLOBALS` key compared
     * verbatim (`$GLOBALS['$post']` and `$GLOBALS['post']` are distinct
     * keys, and only the latter is the `$post` global).
     */
    private static function protectedGlobal(string $bare): ?string
    {
        if (in_array($bare, self::OVERRIDE_ALLOWED, strict: true)) {
            return null;
        }

        return in_array($bare, Lists::WP_GLOBAL_VARIABLES, strict: true)
        || in_array($bare, self::EXTRA_GLOBALS, strict: true)
            ? $bare
            : null;
    }
}
