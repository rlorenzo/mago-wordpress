<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Settings;

use function ltrim;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function stripos;
use function substr;

/**
 * Ports `WordPress.NamingConventions.PrefixAllGlobals`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PrefixAllGlobalsRule implements Rule
{
    /** `define()` declares a constant; the rest declare a hook. */
    private const CHECKED_FUNCTIONS = [
        'define',
        'do_action',
        'apply_filters',
        'do_action_ref_array',
        'apply_filters_ref_array',
    ];

    /** @var list<string> */
    private readonly array $prefixes;

    /** @var null|array<string, true> */
    private ?array $wantedCalls = null;

    public function __construct(Settings $settings)
    {
        $this->prefixes = $settings->prefixes;
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prefix-all-globals',
            name: 'Prefix all globals',
            description: 'Reports global-namespace functions, classes, interfaces, traits, enums, constants and hook names that do not start with a configured plugin/theme prefix. WordPress plugins and themes share one global namespace. The rule is inert until the `prefixes` setting is configured, and it does not check code declared inside a namespace.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [
                NodeKind::Function,
                NodeKind::Class_,
                NodeKind::Interface,
                NodeKind::Trait,
                NodeKind::Enum,
                NodeKind::Constant,
                NodeKind::FunctionCall,
                // Not otherwise dispatched on; targeting it keeps enclosing
                // namespaces in the snapshot for inNamedNamespace(), since a
                // snapshot only materializes the subtrees a rule targets.
                NodeKind::Namespace,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($this->prefixes === []) {
            return;
        }

        // Namespaced code is already isolated from the global namespace.
        if ($this->inNamedNamespace($context->file, $context->node)) {
            return;
        }

        match ($context->node->kind) {
            NodeKind::Function => $this->checkDeclared($context, 'function'),
            NodeKind::Class_ => $this->checkDeclared($context, 'class'),
            NodeKind::Interface => $this->checkDeclared($context, 'interface'),
            NodeKind::Trait => $this->checkDeclared($context, 'trait'),
            NodeKind::Enum => $this->checkDeclared($context, 'enum'),
            NodeKind::Constant => $this->checkConstantStatement($context),
            NodeKind::FunctionCall => $this->checkCall($context),
            default => null,
        };
    }

    /**
     * Checks the name of a function, class, interface, trait or enum declaration.
     */
    private function checkDeclared(LintContext $context, string $kind): void
    {
        $identifier = $this->declaredIdentifier($context->file, $context->node);
        if ($identifier === null) {
            return;
        }

        $this->checkSymbol($context, $kind, $context->file->getText($identifier), $identifier->span);
    }

    /**
     * Checks every name in a `const A = 1, B = 2;` statement.
     */
    private function checkConstantStatement(LintContext $context): void
    {
        foreach ($context->file->getDescendants($context->node, NodeKind::ConstantItem) as $item) {
            $identifier = $context->file->getChildren($item)[0] ?? null;
            if ($identifier === null || $identifier->kind !== NodeKind::LocalIdentifier) {
                continue;
            }

            $this->checkSymbol($context, 'constant', $context->file->getText($identifier), $identifier->span);
        }
    }

    /**
     * Checks a call to `define()`, `do_action()`, `apply_filters()`,
     * `do_action_ref_array()` or `apply_filters_ref_array()`.
     */
    private function checkCall(LintContext $context): void
    {
        $this->wantedCalls ??= Calls::normalizeAll(self::CHECKED_FUNCTIONS);

        $name = Calls::matchWanted($context->file, $context->node, $this->wantedCalls);
        if ($name === null) {
            return;
        }

        $call = CallExpression::fromNode($context->file, $context->node);
        $string = Calls::argument($context->file, $call, 0, $name === 'define' ? 'constant_name' : 'hook_name');
        if ($string === null) {
            return;
        }

        $value = Values::literalString($context->file, $string);
        if ($value === null || $value === '') {
            return;
        }

        if ($name === 'define') {
            // A leading backslash denotes the global namespace; any other
            // backslash means the constant is explicitly namespaced.
            $constant = str_starts_with($value, '\\') ? substr($value, offset: 1) : $value;
            if (!str_contains($constant, '\\')) {
                $this->checkSymbol($context, 'constant', $constant, $string->span);
            }

            return;
        }

        $this->checkSymbol($context, 'hook', $value, $string->span);
    }

    private function checkSymbol(LintContext $context, string $kind, string $name, Span $span): void
    {
        if ($name === '' || str_starts_with($name, '__')) {
            // Never flag PHP magic names (`__construct`, `__DIR__`, etc.).
            return;
        }

        if ($this->isPrefixed($name)) {
            return;
        }

        $example = rtrim($this->prefixes[0], characters: '_') . '_' . ltrim($name, characters: '_');

        $context->report(Issue::new(
            "Global {$kind} `{$name}` is not prefixed.",
            $span,
            "This {$kind} name lacks a plugin/theme prefix.",
        )->withNote(
            'WordPress plugins and themes share a single global namespace; unprefixed global symbols can collide with WordPress core or other plugins.',
        )->withHelp("Rename it to start with your prefix, e.g. `{$example}`."));
    }

    /**
     * Whether $name starts with one of the configured prefixes.
     *
     * The comparison ignores case, and a single leading underscore on the
     * symbol name is ignored (e.g. prefix `myplugin` accepts `_myplugin_internal`).
     */
    private function isPrefixed(string $name): bool
    {
        $name = str_starts_with($name, '_') ? substr($name, offset: 1) : $name;

        foreach ($this->prefixes as $prefix) {
            if (stripos($name, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $node sits inside a `namespace` declaration that has a name.
     *
     * A bracketed global namespace block (`namespace { ... }`) has no name,
     * so it does not count: its contents are still checked.
     */
    private function inNamedNamespace(SourceFile $file, Node $node): bool
    {
        foreach ($file->getAncestors($node) as $ancestor) {
            if ($ancestor->kind === NodeKind::Namespace) {
                return $this->declaredIdentifier($file, $ancestor) !== null;
            }
        }

        return false;
    }

    /**
     * Returns the name identifier of a declaration: its direct identifier
     * child. An attribute's name sits deeper, inside the attribute list.
     */
    private function declaredIdentifier(SourceFile $file, Node $node): ?Node
    {
        foreach ($file->getChildren($node) as $child) {
            // A namespace name is an `Identifier`; other declarations use `LocalIdentifier`.
            if ($child->kind === NodeKind::LocalIdentifier || $child->kind === NodeKind::Identifier) {
                return $child;
            }
        }

        return null;
    }
}
