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
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function array_map;
use function implode;
use function in_array;
use function ltrim;
use function preg_quote;
use function str_contains;
use function strcasecmp;
use function trim;

/**
 * Ports `WordPress.DB.RestrictedClasses`.
 *
 * The sniff flags `Lists::DB_RESTRICTED_CLASSES` (mysqli, PDO, PDOStatement)
 * used with `new`, `extends`, `implements`, or after `::`, resolved against
 * the current namespace the same way PHP resolves a class name (unlike a
 * function or constant name, which falls back to the global namespace).
 * `exclude` (a WPCS ruleset property to turn off individual groups) has no
 * `Settings` field, so it is not supported; every match always reports.
 */
final class DbRestrictedClassesRule implements Rule
{
    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/db-restricted-classes',
            name: 'DB restricted class',
            description: 'Reports usage of the mysqli, PDO and PDOStatement classes.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [
                NodeKind::Instantiation,
                NodeKind::Extends,
                NodeKind::Implements,
                NodeKind::StaticMethodCall,
                NodeKind::StaticPropertyAccess,
                NodeKind::ClassConstantAccess,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $this->gate ??= new FileGate(pattern: self::buildGatePattern());
        if (!$this->gate->passes($context->file)) {
            return;
        }

        match ($context->node->kind) {
            NodeKind::Extends, NodeKind::Implements => $this->inspectNameList($context),
            NodeKind::Instantiation => $this->inspectInstantiation($context),
            default => $this->inspectSelector($context),
        };
    }

    /**
     * `extends`/`implements` name each restricted base directly as a child,
     * skipping only the leading keyword. `implements` can list several.
     */
    private function inspectNameList(LintContext $context): void
    {
        foreach ($context->file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                continue;
            }

            $this->reportIfRestricted($context, $child);
        }
    }

    /**
     * `new`'s class name is the first child that is neither the `new`
     * keyword nor an argument list; a hierarchy keyword (`self`, `static`,
     * `parent`) parses as a `Keyword` there too, so it is skipped the same
     * way the sniff skips it.
     */
    private function inspectInstantiation(LintContext $context): void
    {
        foreach ($context->file->getChildren($context->node) as $child) {
            if (in_array(
                $child->kind,
                [NodeKind::Keyword, NodeKind::ArgumentList, NodeKind::PartialArgumentList],
                strict: true,
            )) {
                continue;
            }

            $this->reportIfRestricted($context, $child);

            return;
        }
    }

    /**
     * `::`, on a static call, a static property, or a class constant, keeps
     * the class-name reference as its first child.
     */
    private function inspectSelector(LintContext $context): void
    {
        $target = $context->file->getChildren($context->node)[0] ?? null;
        if ($target !== null) {
            $this->reportIfRestricted($context, $target);
        }
    }

    private function reportIfRestricted(LintContext $context, Node $target): void
    {
        $name = self::resolvedClassName($context, $target);
        if ($name === null) {
            return;
        }

        foreach (Lists::DB_RESTRICTED_CLASSES as $class) {
            if (strcasecmp($name, $class) !== 0) {
                continue;
            }

            $context->report(Issue::new(
                "Accessing the database directly through {$class} should be avoided.",
                $target->span,
            )->withHelp('Use the $wpdb object and its associated methods instead.'));

            return;
        }
    }

    /**
     * The fully qualified name a class-name reference resolves to, without
     * a leading backslash. PHP resolves an unqualified or `namespace\`
     * class name against the file's namespace, so a resolved name is
     * trusted whenever one is available. A hierarchy keyword parses as a
     * `Keyword` node, not a name node, and never resolves, matching the
     * sniff's own exclusion of `self`/`static`/`parent`.
     */
    private static function resolvedClassName(LintContext $context, Node $target): ?string
    {
        $resolved = $context->file->getResolvedName($target)?->name;
        if ($resolved !== null) {
            return ltrim($resolved, characters: '\\');
        }

        $text = trim($context->file->getText($target));

        return str_contains($text, '\\') ? ltrim($text, characters: '\\') : null;
    }

    private static function buildGatePattern(): string
    {
        $alternation = implode('|', array_map(static fn(string $name): string => preg_quote(
            $name,
            delimiter: '/',
        ), Lists::DB_RESTRICTED_CLASSES));

        return "/\\b(?:{$alternation})\\b/i";
    }
}
