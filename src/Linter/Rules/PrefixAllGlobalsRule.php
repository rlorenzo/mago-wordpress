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
use Rlorenzo\MagoWordPress\Internal\DocBlocks;
use Rlorenzo\MagoWordPress\Internal\GlobalWrites;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Strings;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Internal\WordPress\PrefixAllowlists;
use Rlorenzo\MagoWordPress\Internal\WordPress\TestClasses;
use Rlorenzo\MagoWordPress\Settings;
use WeakMap;

use function array_key_exists;
use function array_map;
use function function_exists;
use function in_array;
use function ltrim;
use function mb_strlen;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function stripos;
use function strlen;
use function strtolower;
use function substr;
use function ucfirst;

use const PHP_INT_MAX;

/**
 * Ports `WordPress.NamingConventions.PrefixAllGlobals`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PrefixAllGlobalsRule implements Rule
{
    private const SNIFF = 'WordPress.NamingConventions.PrefixAllGlobals';

    /** `define()` declares a constant; the rest declare a hook. */
    private const CHECKED_FUNCTIONS = [
        'define',
        'do_action',
        'apply_filters',
        'do_action_ref_array',
        'apply_filters_ref_array',
    ];

    /** Prefixes WPCS refuses outright (`ForbiddenPrefixPassed`). */
    private const FORBIDDEN_PREFIXES = ['wordpress', 'wp', '_', 'php'];

    /** Shorter prefixes are not unique enough (`ShortPrefixPassed`). */
    private const MIN_PREFIX_LENGTH = 3;

    private const CLASS_LIKE_KINDS = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    private const SUPERGLOBALS = [
        'GLOBALS',
        '_COOKIE',
        '_ENV',
        '_FILES',
        '_GET',
        '_POST',
        '_REQUEST',
        '_SERVER',
        '_SESSION',
    ];

    /** @var list<string> Valid configured prefixes; invalid ones are dropped, as WPCS does. */
    private readonly array $prefixes;

    /** @var list<array{string, string}> WPCS message code and message about each rejected prefix, reported at the top of every file. */
    private readonly array $prefixProblems;

    /** @var list<string> Regexes matching a namespace name that starts with a prefix. */
    private readonly array $namespacePatterns;

    /** @var list<string> WPCS `custom_test_classes`, lowercased. */
    private readonly array $customTestClasses;

    /** @var null|array<string, true> */
    private ?array $wantedCalls = null;

    /** @var WeakMap<SourceFile, array<int, true>> `@deprecated` docblock ends per file. */
    private WeakMap $deprecatedStarts;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->customTestClasses = $settings->customList('custom-test-classes');
        $prefixes = [];
        $problems = [];
        foreach ($settings->prefixes as $prefix) {
            if (in_array(strtolower($prefix), self::FORBIDDEN_PREFIXES, strict: true)) {
                $problems[] = ['ForbiddenPrefixPassed', "The `{$prefix}` prefix is not allowed."];
                continue;
            }

            if ((function_exists('mb_strlen') ? mb_strlen($prefix) : strlen($prefix)) < self::MIN_PREFIX_LENGTH) {
                $problems[] = [
                    'ShortPrefixPassed',
                    "The `{$prefix}` prefix is too short. Short prefixes are not unique enough and may cause name collisions with other code.",
                ];
                continue;
            }

            if (preg_match('`^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*$`', $prefix) !== 1) {
                $problems[] = [
                    'InvalidPrefixPassed',
                    "The `{$prefix}` prefix is not a valid namespace/function/class/variable/constant prefix in PHP.",
                ];
                continue;
            }

            $prefixes[] = $prefix;
        }

        $this->prefixes = $prefixes;
        $this->prefixProblems = $problems;
        $this->namespacePatterns = array_map(self::namespacePattern(...), $prefixes);
        $this->deprecatedStarts = new WeakMap();
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prefix-all-globals',
            name: 'Prefix all globals',
            description: 'Reports global-namespace functions, classes, interfaces, traits, enums, constants, global variables and hook names that do not start with a configured plugin/theme prefix. WordPress plugins and themes share one global namespace. The rule is inert until the `prefixes` setting is configured; the prefixes `wordpress`, `wp`, `_` and `php` and prefixes shorter than three characters are reported at the top of each file and ignored. Inside a namespace only `define()` constants and hook names are checked, since those stay global, and the namespace name itself must be prefixed. Global variable writes are checked in the top-level scope, in functions that import the variable with `global`, and through `$GLOBALS[...]` anywhere; superglobals and WordPress core globals are exempt. A constant or hook name built dynamically is reported unless its leading literal part is prefixed. Pluggable functions and classes, overridable core constants, allowed core hooks, PHP built-in names, functions documented as @deprecated and unit test classes are exempt.',
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
                NodeKind::Namespace,
                // Only so test classes show up in a call's ancestors.
                NodeKind::AnonymousClass,
                // Also gives every node its parent chain for the variable scope walks.
                ...($this->prefixProblems === [] && $this->prefixes === [] ? [] : [NodeKind::Program]),
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $kind = $context->node->kind;
        if ($kind === NodeKind::Program) {
            $this->reportPrefixProblems($context);
            if ($this->prefixes !== []) {
                $this->checkVariables($context);
            }

            return;
        }

        if ($this->prefixes === [] || $kind === NodeKind::AnonymousClass) {
            return;
        }

        if ($kind === NodeKind::Namespace) {
            $this->checkNamespace($context);

            return;
        }

        // `define()` and hook names stay global even inside a namespace.
        if ($kind === NodeKind::FunctionCall) {
            $this->checkCall($context);

            return;
        }

        // PHP namespaces these declarations, isolating them from the global namespace.
        if ($this->inNamedNamespace($context->file, $context->node)) {
            return;
        }

        match ($kind) {
            NodeKind::Function => $this->checkDeclared($context, 'function'),
            NodeKind::Class_ => $this->checkDeclared($context, 'class'),
            NodeKind::Interface => $this->checkDeclared($context, 'interface'),
            NodeKind::Trait => $this->checkDeclared($context, 'trait'),
            NodeKind::Enum => $this->checkDeclared($context, 'enum'),
            NodeKind::Constant => $this->checkConstantStatement($context),
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

        $name = $context->file->getText($identifier);
        $exempt = match ($kind) {
            'function' => PrefixAllowlists::isAllowedFunction($name),
            'class' => PrefixAllowlists::isAllowedClass($name),
            default => PrefixAllowlists::isNativeClassLike($name),
        };

        if (!$exempt) {
            $this->checkSymbol($context, $kind, $name, $identifier->span);
        }
    }

    /**
     * Checks that a namespace name starts with a prefix. A prefix without a
     * backslash may use `\` in place of any of its non-word characters, so
     * prefix `my_plugin` accepts `My\Plugin`.
     */
    private function checkNamespace(LintContext $context): void
    {
        $identifier = $this->declaredIdentifier($context->file, $context->node);
        if ($identifier === null) {
            return;
        }

        $name = $context->file->getText($identifier);
        foreach ($this->namespacePatterns as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return;
            }
        }

        $this->checkSymbol($context, 'namespace', $name, $identifier->span);
    }

    private static function namespacePattern(string $prefix): string
    {
        // A trailing separator (`my_plugin_`, `acme\tools\`) must still match the root namespace itself.
        $trimmed = rtrim($prefix, characters: '_\\');
        $boundary = $trimmed !== $prefix ? '(?:[\\\\_]|$)' : '';
        $quoted = str_contains($trimmed, '\\')
            ? preg_quote($trimmed, delimiter: '`')
            : preg_replace_callback(
                '`[_\W]`',
                static fn(array $match): string => '[\\\\' . preg_quote($match[0], delimiter: '`') . ']',
                $trimmed,
            ) ?? preg_quote($trimmed, delimiter: '`');

        return '`^' . $quoted . $boundary . '`i';
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

            $name = $context->file->getText($identifier);
            if (!PrefixAllowlists::isAllowedConstant($name)) {
                $this->checkSymbol($context, 'constant', $name, $identifier->span);
            }
        }
    }

    /**
     * Checks global variable writes: assignments, foreach bindings and
     * destructuring targets in the top-level scope, writes to variables a
     * function imported with `global`, and `$GLOBALS[...]` writes anywhere.
     * An array element write counts as a write to its base variable.
     */
    private function checkVariables(LintContext $context): void
    {
        $file = $context->file;
        $imports = GlobalWrites::imports($file, $context->node);

        foreach (GlobalWrites::targets($file, $context->node) as $target) {
            $this->checkVariableWrite($context, $target, $imports);
        }
    }

    /**
     * @param array<int, array<string, int>> $imports
     */
    private function checkVariableWrite(LintContext $context, Node $target, array $imports): void
    {
        $file = $context->file;
        $key = null;
        while ($target->kind === NodeKind::ArrayAccess || $target->kind === NodeKind::ArrayAppend) {
            $parts = $file->getChildren($target);
            $key = $target->kind === NodeKind::ArrayAccess ? $parts[1] ?? null : null;
            $target = Values::unwrap($file, $parts[0] ?? $target);
        }

        $variable = $file->getChildren($target)[0] ?? null;
        if ($target->kind !== NodeKind::Variable || $variable === null) {
            return;
        }

        // An arrow function's variables are skipped entirely, as WPCS does.
        $scope = GlobalWrites::nearestScope($file, $variable);
        if ($scope?->kind === NodeKind::ArrowFunction) {
            return;
        }

        $name = $file->getText($variable);
        if ($name === '$GLOBALS') {
            if ($key !== null) {
                $this->checkGlobalsKey($context, $key);
            }

            return;
        }

        // A function's variables are local unless imported with `global` before the write.
        $isDynamic = $variable->kind !== NodeKind::DirectVariable;
        $importKey = $isDynamic ? GlobalWrites::ANY_IMPORT : $name;
        if ($scope !== null && ($imports[$scope->id][$importKey] ?? PHP_INT_MAX) >= $variable->span->start) {
            return;
        }

        if ($isDynamic) {
            $this->reportDynamicVariable($context, $name, $variable);

            return;
        }

        if (!$this->isAllowedVariable(substr($name, offset: 1))) {
            $this->reportVariable($context, $name, $variable);
        }
    }

    /**
     * Checks a `$GLOBALS[...]` key by its leading literal text; a key that
     * does not start with a literal cannot be verified.
     */
    private function checkGlobalsKey(LintContext $context, Node $key): void
    {
        $leading = $this->leadingLiteral($context->file, $key);
        if ($leading === null) {
            $this->reportDynamicVariable($context, $context->file->getText($key), $key);

            return;
        }

        if (!$this->isAllowedVariable($leading)) {
            $this->reportVariable($context, '$' . $leading, $key);
        }
    }

    private function isAllowedVariable(string $name): bool
    {
        return (
            in_array($name, self::SUPERGLOBALS, strict: true)
            || in_array($name, Lists::WP_GLOBAL_VARIABLES, strict: true)
            || $this->isPrefixed($name)
        );
    }

    private function reportVariable(LintContext $context, string $name, Node $node): void
    {
        $this->reportVariableIssue(
            $context,
            $node,
            Issue::new(
                "Global variable `{$name}` is not prefixed.",
                $node->span,
                'This global variable lacks a plugin/theme prefix.',
            ),
        );
    }

    private function reportDynamicVariable(LintContext $context, string $name, Node $node): void
    {
        $this->reportVariableIssue(
            $context,
            $node,
            Issue::new(
                "Global variable name `{$name}` is built dynamically, so its prefix cannot be verified.",
                $node->span,
                'This global variable name does not start with a literal prefix.',
            ),
        );
    }

    /**
     * Reports a write unless it sits in a test class.
     */
    private function reportVariableIssue(LintContext $context, Node $node, Issue $issue): void
    {
        if ($this->isExempt($context, $node)) {
            return;
        }

        $this->report->issue(
            $context,
            $issue
                ->withNote(
                    'WordPress plugins and themes share a single global namespace; unprefixed global variables can collide with WordPress core or other plugins.',
                )
                ->withHelp("Rename it to start with your prefix, e.g. `\${$this->prefixes[0]}_name`."),
            [self::SNIFF . '.NonPrefixedVariableFound'],
        );
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

        $isDefine = $name === 'define';
        $call = CallExpression::fromNode($context->file, $context->node);
        $string = Calls::argument($context->file, $call, 0, $isDefine ? 'constant_name' : 'hook_name');
        if ($string === null) {
            return;
        }

        $value = Values::literalString($context->file, $string);
        if ($value === null) {
            $this->checkDynamicName($context, $isDefine ? 'constant' : 'hook', $string);

            return;
        }

        if ($value === '') {
            return;
        }

        if ($isDefine) {
            // A leading backslash denotes the global namespace; any other
            // backslash means the constant is explicitly namespaced.
            $constant = str_starts_with($value, '\\') ? substr($value, offset: 1) : $value;
            if (!str_contains($constant, '\\') && !PrefixAllowlists::isAllowedConstant($constant)) {
                $this->checkSymbol($context, 'constant', $constant, $string->span);
            }

            return;
        }

        if (!array_key_exists($value, PrefixAllowlists::CORE_HOOKS)) {
            $this->checkSymbol($context, 'hook', $value, $string->span);
        }
    }

    /**
     * Checks a constant or hook name that is not a single literal string,
     * the way WPCS does: the name passes if its text, or its leading
     * literal part, starts with a prefix. A name whose leading part is not
     * a literal cannot be verified and gets a warning of its own.
     */
    private function checkDynamicName(LintContext $context, string $kind, Node $argument): void
    {
        $file = $context->file;
        $text = $file->getText($argument);
        // A backslash means a namespaced (or unreachable) constant.
        if ($kind === 'constant' && str_contains($text, '\\')) {
            return;
        }

        if ($this->isPrefixed(preg_replace('`^([\'"])(.*)\1$`Ds', replacement: '$2', subject: $text) ?? $text)) {
            return;
        }

        $leading = $this->leadingLiteral($file, $argument);
        if ($leading !== null) {
            $this->checkSymbol($context, $kind, $leading, $argument->span);

            return;
        }

        if ($this->isExempt($context)) {
            return;
        }

        $subject = $kind === 'constant' ? 'Constant' : 'Hook';
        $this->report->issue(
            $context,
            Issue::new(
                "{$subject} name `{$text}` is built dynamically, so its prefix cannot be verified.",
                $argument->span,
                "This {$kind} name does not start with a literal prefix.",
            )->withNote(
                'WordPress plugins and themes share a single global namespace; unprefixed global names can collide with WordPress core or other plugins.',
            )->withHelp("Start the name with a literal prefix, e.g. `'{$this->prefixes[0]}_' . \$name`."),
            [self::SNIFF . ($kind === 'constant' ? '.VariableConstantNameFound' : '.DynamicHooknameFound')],
        );
    }

    /**
     * Returns the literal text a name expression starts with: a whole
     * leading literal string, or the part of a leading interpolated string
     * before its first variable. NULL when the name starts with anything
     * else, including a variable inside the interpolated string.
     */
    private function leadingLiteral(SourceFile $file, Node $argument): ?string
    {
        $node = Values::unparenthesize($file, $argument);
        while ($node->kind !== NodeKind::LiteralString && $node->kind !== NodeKind::CompositeString) {
            $child = $file->getChildren($node)[0] ?? null;
            if ($child === null || $child->span->start !== $node->span->start) {
                return null;
            }

            $node = Values::unparenthesize($file, $child);
        }

        if ($node->kind === NodeKind::LiteralString) {
            return Values::literalString($file, $node);
        }

        foreach (Strings::compositeParts($file, $node) as $text) {
            return $text === '' ? null : $text;
        }

        return null;
    }

    private function checkSymbol(LintContext $context, string $kind, string $name, Span $span): void
    {
        if ($name === '' || $this->isPrefixed($name) || $this->isExempt($context)) {
            return;
        }

        $example = $kind === 'namespace'
            ? rtrim($this->prefixes[0], characters: '\\') . '\\' . $name
            : rtrim($this->prefixes[0], characters: '_') . '_' . ltrim($name, characters: '_');
        $subject = $kind === 'namespace' ? 'Namespace' : "Global {$kind}";

        $this->report->issue(
            $context,
            Issue::new(
                "{$subject} `{$name}` is not prefixed.",
                $span,
                "This {$kind} name lacks a plugin/theme prefix.",
            )->withNote(
                'WordPress plugins and themes share a single global namespace; unprefixed global symbols can collide with WordPress core or other plugins.',
            )->withHelp("Rename it to start with your prefix, e.g. `{$example}`."),
            [self::SNIFF . '.NonPrefixed' . ($kind === 'hook' ? 'Hookname' : ucfirst($kind)) . 'Found'],
        );
    }

    private function reportPrefixProblems(LintContext $context): void
    {
        $span = ($context->file->getChildren($context->node)[0] ?? $context->node)->span;
        foreach ($this->prefixProblems as [$code, $problem]) {
            $this->report->issue(
                $context,
                Issue::new($problem, $span, 'Invalid `prefixes` setting.')->withHelp(
                    'Configure a distinctive prefix of at least three characters for your plugin or theme.',
                ),
                [self::SNIFF . '.' . $code],
            );
        }
    }

    /**
     * Whether the checked node is a function documented as `@deprecated`,
     * or sits in (or is) a unit test class. Only asked once a name is
     * known to be unprefixed, since both lookups walk the file.
     */
    private function isExempt(LintContext $context, ?Node $node = null): bool
    {
        $file = $context->file;
        $node ??= $context->node;
        if ($node->kind === NodeKind::Function && $this->isDeprecated($file, $node)) {
            return true;
        }

        $namespace = '';
        $classLikes = [];
        foreach ([$node, ...$file->getAncestors($node)] as $candidate) {
            if ($candidate->kind === NodeKind::Namespace) {
                $identifier = $this->declaredIdentifier($file, $candidate);
                $namespace = $identifier === null ? '' : strtolower($file->getText($identifier));
                break;
            }

            if (in_array($candidate->kind, self::CLASS_LIKE_KINDS, strict: true)) {
                $classLikes[] = $candidate;
            }
        }

        foreach ($classLikes as $classLike) {
            if (TestClasses::is($file, $classLike, $namespace, $this->customTestClasses)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a `@deprecated` docblock sits right before the function or
     * one of its leading attributes.
     */
    private function isDeprecated(SourceFile $file, Node $function): bool
    {
        $this->deprecatedStarts[$file] ??= DocBlocks::deprecatedStarts($file);
        $deprecatedStarts = $this->deprecatedStarts[$file];
        if ($deprecatedStarts[$function->span->start] ?? false) {
            return true;
        }

        foreach ($file->getChildren($function) as $child) {
            if ($deprecatedStarts[$child->span->start] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $name starts with one of the configured prefixes, ignoring case.
     */
    private function isPrefixed(string $name): bool
    {
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
