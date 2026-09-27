<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function array_key_exists;
use function array_keys;
use function ltrim;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Ports `WordPress.WP.DiscouragedConstants`.
 *
 * `Program` is a target so every node keeps its parent chain, which the
 * `use const` check below needs to see whether it sits in a namespaced
 * group and whether the statement is a `const` import.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DiscouragedConstantsRule implements Rule
{
    private const SNIFF = 'WordPress.WP.DiscouragedConstants';

    private ?FileGate $gate = null;

    /** @var null|array<string, true> */
    private ?array $defineCall = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/discouraged-constants',
            name: 'Discouraged constants',
            description: 'Reports usage and (re-)declaration of discouraged WordPress constants, such as `STYLESHEETPATH` or `PLUGINDIR`, and recommends the modern replacement.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // Every check needs a discouraged name in the source as a whole word:
        // `define()` only reads a literal string and `use const` names it.
        $this->gate ??= FileGate::forWords(array_keys(Lists::DISCOURAGED_CONSTANTS));
        if (!$this->gate->passes($file)) {
            return;
        }

        // One walk of the whole program, dispatched by kind.
        foreach ($file->getDescendants($context->node) as $node) {
            match ($node->kind) {
                NodeKind::ConstantAccess => $this->checkConstantAccess($context, $file, $node),
                NodeKind::Constant => $this->checkConstantStatement($context, $file, $node),
                NodeKind::UseItem => $this->checkUseItem($context, $file, $node),
                NodeKind::FunctionCall => $this->checkFunctionCall($context, $file, $node),
                default => null,
            };
        }
    }

    private function checkFunctionCall(LintContext $context, SourceFile $file, Node $call): void
    {
        $this->defineCall ??= Calls::normalizeAll(['define']);
        if (Calls::matchWanted($file, $call, $this->defineCall) !== null) {
            $this->checkDefine($context, $file, CallExpression::fromNode($file, $call));
        }
    }

    /**
     * Checks a bare constant reference, e.g. `echo STYLESHEETPATH;`.
     */
    private function checkConstantAccess(LintContext $context, SourceFile $file, Node $access): void
    {
        $identifier = $file->getChildren($access)[0] ?? null;
        if ($identifier === null) {
            return;
        }

        $name = self::globalConstantName($file, $identifier);
        if ($name === null || !array_key_exists($name, Lists::DISCOURAGED_CONSTANTS)) {
            return;
        }

        $this->reportUsage($context, $access, $name);
    }

    /**
     * Checks a top-level `const NAME = value;` statement. Class-like
     * constants are a different node kind (`ClassLikeConstant`) and are
     * never targeted here, matching the sniff excluding OO constants.
     * Inside a named namespace the statement declares a namespaced
     * constant, not the global one, so it is skipped.
     */
    private function checkConstantStatement(LintContext $context, SourceFile $file, Node $statement): void
    {
        if (self::inNamedNamespace($file, $statement)) {
            return;
        }

        foreach ($file->getDescendants($statement, NodeKind::ConstantItem) as $item) {
            $identifier = $file->getChildren($item)[0] ?? null;
            if ($identifier === null || $identifier->kind !== NodeKind::LocalIdentifier) {
                continue;
            }

            $name = $file->getText($identifier);
            if (array_key_exists($name, Lists::DISCOURAGED_CONSTANTS)) {
                $this->reportUsage($context, $identifier, $name);
            }
        }
    }

    /**
     * Checks a `use const NAME [as ALIAS];` import. A group import under a
     * namespace prefix (`use const NS\{NAME};`) is never a bare global
     * reference, whatever the item's own name looks like.
     */
    private function checkUseItem(LintContext $context, SourceFile $file, Node $item): void
    {
        $useStatement = null;
        foreach ($file->getAncestors($item) as $ancestor) {
            if ($ancestor->kind === NodeKind::TypedUseItemList || $ancestor->kind === NodeKind::MixedUseItemList) {
                return;
            }

            if ($ancestor->kind === NodeKind::Use) {
                $useStatement = $ancestor;
                break;
            }
        }

        if ($useStatement === null || preg_match('/^use\s+const\b/i', $file->getText($useStatement)) !== 1) {
            return;
        }

        $identifier = $file->getChildren($item)[0] ?? null;
        if ($identifier === null) {
            return;
        }

        $name = self::globalConstantName($file, $identifier);
        if ($name === null || !array_key_exists($name, Lists::DISCOURAGED_CONSTANTS)) {
            return;
        }

        $this->reportUsage($context, $identifier, $name);
    }

    /**
     * Checks a call to `define()` for a discouraged constant name.
     */
    private function checkDefine(LintContext $context, SourceFile $file, CallExpression $call): void
    {
        $nameNode = Calls::argument($file, $call, index: 0, parameter: 'constant_name');
        if ($nameNode === null) {
            return;
        }

        $value = Values::literalString($file, $nameNode);
        if ($value === null || $value === '') {
            return;
        }

        // A leading backslash denotes the global namespace; any other
        // backslash means the constant is explicitly namespaced.
        $constant = str_starts_with($value, '\\') ? substr($value, offset: 1) : $value;
        if (str_contains($constant, '\\') || !array_key_exists($constant, Lists::DISCOURAGED_CONSTANTS)) {
            return;
        }

        $this->reportDeclaration($context, $nameNode, $constant);
    }

    private function reportUsage(LintContext $context, Node $node, string $name): void
    {
        Report::issue(
            $context,
            Issue::new("Found usage of constant `{$name}`.", $node->span)->withHelp(
                "Use {$this->replacementFor($name)} instead.",
            ),
            [self::SNIFF . ".{$name}UsageFound"],
        );
    }

    private function reportDeclaration(LintContext $context, Node $node, string $name): void
    {
        Report::issue(
            $context,
            Issue::new("Found declaration of constant `{$name}`.", $node->span)->withHelp(
                "Use {$this->replacementFor($name)} instead.",
            ),
            [self::SNIFF . ".{$name}DeclarationFound"],
        );
    }

    private function replacementFor(string $name): string
    {
        return Lists::DISCOURAGED_CONSTANTS[$name];
    }

    /**
     * Resolves an identifier to the bare name of the global constant it
     * refers to, or NULL when it cannot statically be a global constant: a
     * namespace-relative name (`Foo\BAR`, `namespace\BAR`) always resolves
     * relative to the current namespace, never to the global one.
     */
    private static function globalConstantName(SourceFile $file, Node $node): ?string
    {
        while ($node->kind === NodeKind::Identifier) {
            $child = $file->getChildren($node)[0] ?? null;
            if ($child === null) {
                return null;
            }

            $node = $child;
        }

        if ($node->kind === NodeKind::LocalIdentifier) {
            return $file->getText($node);
        }

        if ($node->kind !== NodeKind::FullyQualifiedIdentifier) {
            return null;
        }

        $rest = ltrim($file->getText($node), characters: '\\');

        return str_contains($rest, '\\') ? null : $rest;
    }

    /**
     * Whether $node sits inside a `namespace` declaration that has a name
     * (an `Identifier` child). `namespace { ... }` is the global namespace.
     */
    private static function inNamedNamespace(SourceFile $file, Node $node): bool
    {
        foreach ($file->getAncestors($node) as $ancestor) {
            if ($ancestor->kind !== NodeKind::Namespace) {
                continue;
            }

            foreach ($file->getChildren($ancestor) as $child) {
                if ($child->kind === NodeKind::Identifier) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
}
