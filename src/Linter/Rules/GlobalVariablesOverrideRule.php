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
use Rlorenzo\MagoWordPress\Internal\GlobalWrites;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Internal\WordPress\TestClasses;
use Rlorenzo\MagoWordPress\Settings;

use function in_array;
use function substr;
use function trim;

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
    private const SNIFF = 'WordPress.WP.GlobalVariablesOverride';

    /**
     * Globals themes and plugins are expected to set (WPCS `$override_allowed`).
     */
    private const OVERRIDE_ALLOWED = ['content_width', 'wp_cockneyreplace'];

    /**
     * Globals WordPress core sets that WPCS's generated list lacks.
     */
    private const EXTRA_GLOBALS = ['query_string'];

    private const CLASS_LIKE_KINDS = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    public function __construct(
        private readonly Report $report,
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/global-variables-override',
            name: 'Global variables override',
            description: 'Reports writes that overwrite WordPress-protected global variables such as $post, $wp_query, or $wpdb. This covers direct assignments (including compound assignments), foreach key/value bindings, list/array destructuring targets, however deeply nested, and writes to an element of the variable such as $post[\'key\'] = .... A write is flagged in the top-level scope, or inside a function-like scope where the variable was imported with a global statement. Writes to $GLOBALS[...] with a protected key (a string literal, or a concatenation of them) are flagged anywhere; the key is compared verbatim, so $GLOBALS[\'$post\'] is a different key from $GLOBALS[\'post\']. Code inside unit-test classes (those extending WP_UnitTestCase, PHPUnit\'s TestCase and the like) is skipped.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $imports = GlobalWrites::imports($file, $context->node);

        foreach (GlobalWrites::targets($file, $context->node) as $target) {
            // `$var[...] = ...` overwrites part of `$var`, which WPCS counts as a write to it.
            $base = $target;
            $key = null;
            while ($base->kind === NodeKind::ArrayAccess || $base->kind === NodeKind::ArrayAppend) {
                $parts = $file->getChildren($base);
                $key = $base->kind === NodeKind::ArrayAccess ? $parts[1] ?? null : null;
                $base = Values::unwrap($file, $parts[0] ?? $base);
            }

            $variable = $file->getChildren($base)[0] ?? $base;
            if ($base->kind !== NodeKind::Variable || $variable->kind !== NodeKind::DirectVariable) {
                continue;
            }

            if ($file->getText($variable) !== '$GLOBALS') {
                $this->checkVariableTarget($context, $file, $target, $variable, $imports);
                continue;
            }

            if ($key !== null) {
                $this->checkGlobalsKey($context, $file, $target, $key);
            }
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

        // `treat_files_as_scoped`: the file scope needs a `global` import too, like a function.
        $scope = GlobalWrites::nearestScope($file, $variable);
        if ($scope === null && $this->settings->treatFilesAsScoped) {
            $scope = $context->node;
        }

        if ($scope !== null && ($importsByScope[$scope->id][$text] ?? PHP_INT_MAX) >= $variable->span->start) {
            return;
        }

        $this->report($context, $spanNode, $name);
    }

    private function checkGlobalsKey(LintContext $context, SourceFile $file, Node $spanNode, Node $key): void
    {
        $keyValue = self::literalKey($file, $key);
        if ($keyValue === null) {
            return;
        }

        $name = self::protectedGlobal($keyValue);
        if ($name === null) {
            return;
        }

        $this->report($context, $spanNode, $name);
    }

    /**
     * The key's value when it is a string literal or a `.` concatenation of
     * them, as WPCS joins `'p' . 'age'`; null for anything dynamic.
     */
    private static function literalKey(SourceFile $file, Node $key): ?string
    {
        $key = Values::unparenthesize($file, $key);
        if ($key->kind !== NodeKind::Binary) {
            return Values::literalString($file, $key);
        }

        [$lhs, $operator, $rhs] = $file->getChildren($key) + [null, null, null];
        if ($lhs === null || $operator === null || $rhs === null || trim($file->getText($operator)) !== '.') {
            return null;
        }

        $left = self::literalKey($file, $lhs);
        $right = self::literalKey($file, $rhs);

        return $left === null || $right === null ? null : $left . $right;
    }

    private function report(LintContext $context, Node $spanNode, string $name): void
    {
        // WPCS skips test classes whole, so tests may set up any global.
        if ($this->inTestClass($context->file, $spanNode)) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                "Assignment overwrites the WordPress global variable \${$name}.",
                $spanNode->span,
                "\${$name} is a WordPress global and must not be overwritten",
            )->withNote(
                'WordPress core and other plugins rely on this global; overwriting it can break them in unpredictable ways.',
            )->withHelp('Use a differently named local variable, or the appropriate WordPress API instead.'),
            [self::SNIFF . '.Prohibited'],
        );
    }

    private function inTestClass(SourceFile $file, Node $node): bool
    {
        $namespace = TestClasses::namespaceOf($file, $node);
        $custom = $this->settings->customList('custom-test-classes');
        foreach ($file->getAncestors($node) as $ancestor) {
            if (
                in_array($ancestor->kind, self::CLASS_LIKE_KINDS, strict: true)
                && TestClasses::is($file, $ancestor, $namespace, $custom)
            ) {
                return true;
            }
        }

        return false;
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
