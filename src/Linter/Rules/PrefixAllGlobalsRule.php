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
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\PrefixAllowlists;
use Rlorenzo\MagoWordPress\Settings;

use function array_key_exists;
use function array_map;
use function explode;
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
use function strspn;
use function strtolower;
use function substr;

/**
 * Ports `WordPress.NamingConventions.PrefixAllGlobals`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
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

    /** Prefixes WPCS refuses outright (`ForbiddenPrefixPassed`). */
    private const FORBIDDEN_PREFIXES = ['wordpress', 'wp', '_', 'php'];

    /** Shorter prefixes are not unique enough (`ShortPrefixPassed`). */
    private const MIN_PREFIX_LENGTH = 3;

    /**
     * Class names that mark a class as a unit test (WPCS `IsUnitTestTrait`), lowercased.
     */
    private const TEST_CLASSES = [
        'wp_unittestcase',
        'wp_unittestcase_base',
        'phpunit_adapter_testcase',
        'wp_ajax_unittestcase',
        'wp_canonical_unittestcase',
        'wp_font_face_unittestcase',
        'wp_test_rest_controller_testcase',
        'wp_test_rest_post_type_controller_testcase',
        'wp_test_rest_testcase',
        'wp_test_xml_testcase',
        'wp_xmlrpc_unittestcase',
        'phpunit_framework_testcase',
        'phpunit\\framework\\testcase',
        'testcase',
    ];

    private const CLASS_LIKE_KINDS = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    /** @var list<string> Valid configured prefixes; invalid ones are dropped, as WPCS does. */
    private readonly array $prefixes;

    /** @var list<string> Messages about configured prefixes, reported at the top of every file. */
    private readonly array $prefixProblems;

    /** @var list<string> Regexes matching a namespace name that starts with a prefix. */
    private readonly array $namespacePatterns;

    /** @var null|array<string, true> */
    private ?array $wantedCalls = null;

    public function __construct(Settings $settings)
    {
        $prefixes = [];
        $problems = [];
        foreach ($settings->prefixes as $prefix) {
            if (in_array($prefix, self::FORBIDDEN_PREFIXES, strict: true)) {
                $problems[] = "The `{$prefix}` prefix is not allowed.";
                continue;
            }

            if (mb_strlen($prefix) < self::MIN_PREFIX_LENGTH) {
                $problems[] = "The `{$prefix}` prefix is too short. Short prefixes are not unique enough and may cause name collisions with other code.";
                continue;
            }

            if (preg_match('`^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*$`', $prefix) !== 1) {
                $problems[] = "The `{$prefix}` prefix is not a valid namespace/function/class/variable/constant prefix in PHP.";
            }

            $prefixes[] = $prefix;
        }

        $this->prefixes = $prefixes;
        $this->prefixProblems = $problems;
        $this->namespacePatterns = array_map(self::namespacePattern(...), $prefixes);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prefix-all-globals',
            name: 'Prefix all globals',
            description: 'Reports global-namespace functions, classes, interfaces, traits, enums, constants and hook names that do not start with a configured plugin/theme prefix. WordPress plugins and themes share one global namespace. The rule is inert until the `prefixes` setting is configured; the prefixes `wordpress`, `wp`, `_` and `php` and prefixes shorter than three characters are reported at the top of each file and ignored. Inside a namespace only `define()` constants and hook names are checked, since those stay global, and the namespace name itself must be prefixed. A constant or hook name built dynamically is reported unless its leading literal part is prefixed. Pluggable functions and classes, overridable core constants, allowed core hooks, PHP built-in names, functions documented as @deprecated and unit test classes are exempt.',
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
                ...($this->prefixProblems === [] ? [] : [NodeKind::Program]),
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $kind = $context->node->kind;
        if ($kind === NodeKind::Program) {
            $this->reportPrefixProblems($context);

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
        if ($value === null) {
            $this->checkDynamicName($context, $name === 'define' ? 'constant' : 'hook', $string);

            return;
        }

        if ($value === '') {
            return;
        }

        if ($name === 'define') {
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
        $context->report(Issue::new(
            "{$subject} name `{$text}` is built dynamically, so its prefix cannot be verified.",
            $argument->span,
            "This {$kind} name does not start with a literal prefix.",
        )->withNote(
            'WordPress plugins and themes share a single global namespace; unprefixed global names can collide with WordPress core or other plugins.',
        )->withHelp("Start the name with a literal prefix, e.g. `'{$this->prefixes[0]}_' . \$name`."));
    }

    /**
     * Returns the literal text a name expression starts with: a whole
     * leading literal string, or the part of a leading interpolated string
     * before its first variable. NULL when the name starts with anything
     * else, including a variable inside the interpolated string.
     */
    private function leadingLiteral(SourceFile $file, Node $argument): ?string
    {
        $node = $argument;
        while ($node->kind !== NodeKind::LiteralString && $node->kind !== NodeKind::CompositeString) {
            $child = $file->getChildren($node)[0] ?? null;
            if ($child === null || $child->span->start !== $argument->span->start) {
                return null;
            }

            $node = $child;
        }

        if ($node->kind === NodeKind::LiteralString) {
            return Values::literalString($file, $node);
        }

        if (($file->getChildren($node)[0] ?? null)?->kind !== NodeKind::InterpolatedString) {
            return null;
        }

        $leading = rtrim(explode('$', substr($file->getText($node), offset: 1))[0], characters: '{');

        return $leading === '' ? null : $leading;
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

        $context->report(Issue::new(
            "{$subject} `{$name}` is not prefixed.",
            $span,
            "This {$kind} name lacks a plugin/theme prefix.",
        )->withNote(
            'WordPress plugins and themes share a single global namespace; unprefixed global symbols can collide with WordPress core or other plugins.',
        )->withHelp("Rename it to start with your prefix, e.g. `{$example}`."));
    }

    private function reportPrefixProblems(LintContext $context): void
    {
        $span = ($context->file->getChildren($context->node)[0] ?? $context->node)->span;
        foreach ($this->prefixProblems as $problem) {
            $context->report(Issue::new($problem, $span, 'Invalid `prefixes` setting.')->withHelp(
                'Configure a distinctive prefix of at least three characters for your plugin or theme.',
            ));
        }
    }

    /**
     * Whether the checked node is a function documented as `@deprecated`,
     * or sits in (or is) a unit test class. Only asked once a name is
     * known to be unprefixed, since both lookups walk the file.
     */
    private function isExempt(LintContext $context): bool
    {
        $file = $context->file;
        $node = $context->node;
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
            if ($this->isTestClass($file, $classLike, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * WPCS `IsUnitTestTrait::is_test_class()`: the class-like is, or
     * extends, a known test class. Names resolve against the namespace
     * only; `use` imports are not followed.
     */
    private function isTestClass(SourceFile $file, Node $classLike, string $namespace): bool
    {
        $identifier = $this->declaredIdentifier($file, $classLike);
        if ($identifier !== null && self::isKnownTestClass($namespace, $file->getText($identifier))) {
            return true;
        }

        foreach ($file->getChildren($classLike) as $child) {
            if ($child->kind !== NodeKind::Extends) {
                continue;
            }

            foreach ($file->getDescendants($child) as $name) {
                if (
                    $name->kind === NodeKind::LocalIdentifier
                    || $name->kind === NodeKind::QualifiedIdentifier
                    || $name->kind === NodeKind::FullyQualifiedIdentifier
                ) {
                    return self::isKnownTestClass($namespace, $file->getText($name));
                }
            }
        }

        return false;
    }

    private static function isKnownTestClass(string $namespace, string $name): bool
    {
        $name = strtolower($name);
        $qualified = match (true) {
            str_starts_with($name, '\\') => substr($name, offset: 1),
            $namespace !== '' => $namespace . '\\' . $name,
            default => $name,
        };

        return in_array($qualified, self::TEST_CLASSES, strict: true);
    }

    /**
     * Whether a `@deprecated` docblock sits right before the function or
     * one of its leading attributes.
     */
    private function isDeprecated(SourceFile $file, Node $function): bool
    {
        $starts = [$function->span->start => true];
        foreach ($file->getChildren($function) as $child) {
            $starts[$child->span->start] = true;
        }

        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $end = $trivia->span->end;
            $next = $end + strspn($file->contents, characters: " \t\r\n", offset: $end);
            // PHPCS only tokenizes a tag at the start of a docblock line.
            if (
                ($starts[$next] ?? false)
                && preg_match('~^[ \t]*(?:/\*\*|\*)?[ \t]*@deprecated(?:\s|$)~m', substr(
                    $file->contents,
                    $trivia->span->start,
                    $trivia->span->length(),
                )) === 1
            ) {
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
