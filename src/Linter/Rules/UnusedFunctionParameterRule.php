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
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_key_exists;
use function array_reverse;
use function array_slice;
use function array_values;
use function count;
use function in_array;
use function strtolower;

/**
 * Ports `Generic.CodeAnalysis.UnusedFunctionParameter` with the codes WordPress-Extra keeps:
 * `Found` (a lone unused parameter) and `FoundAfterLastUsed` (unused parameters after the last
 * used one). Extra excludes `FoundBeforeLastUsed` and every code for a method of a class that
 * extends or implements something, so those are not reported. Like the sniff, a parameter counts
 * as used when its variable appears anywhere in the body, nested closures and strings included;
 * a body with no code, an abstract method, a promoted constructor parameter and the magic
 * methods whose signature PHP dictates are skipped. `compact()`, `extract()` and
 * `func_get_args()` do not count as uses, as in the sniff.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class UnusedFunctionParameterRule implements Rule
{
    private const SNIFF = 'Generic.CodeAnalysis.UnusedFunctionParameter';

    private const MAGIC_METHODS = [
        '__destruct',
        '__call',
        '__callstatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
        '__tostring',
        '__set_state',
        '__clone',
        '__debuginfo',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/unused-function-parameter',
            name: 'Unused function parameter',
            description: 'Reports a function parameter the body never uses, when no later parameter is used either.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    /** The whole file, so a method sees its class: a linter snapshot holds only the targeted subtrees. */
    public function lint(LintContext $context): void
    {
        foreach ([NodeKind::Function, NodeKind::Method, NodeKind::Closure, NodeKind::ArrowFunction] as $kind) {
            foreach ($context->file->getNodes($kind) as $function) {
                $this->check($context, $function);
            }
        }
    }

    private function check(LintContext $context, Node $function): void
    {
        $file = $context->file;
        $keyword = null;
        $parameters = [];
        $body = null;
        foreach ($file->getChildren($function) as $child) {
            match ($child->kind) {
                NodeKind::Keyword => $keyword ??= $child,
                NodeKind::FunctionLikeParameterList => $parameters = $file->getChildren($child),
                NodeKind::Block, NodeKind::Expression => $body = $child,
                NodeKind::MethodBody => $body = $file->getFirstDescendant($child, NodeKind::Block),
                default => null,
            };
        }

        if ($keyword === null || $body === null || $parameters === [] || $this->isSkippedMethod($file, $function)) {
            return;
        }

        $used = self::usedVariables($file, $body);
        if ($used === null) {
            return;
        }

        // name => unused; a promoted parameter counts as used.
        $unused = [];
        foreach ($parameters as $parameter) {
            $promoted = false;
            $name = '';
            foreach ($file->getChildren($parameter) as $part) {
                $promoted = $promoted || $part->kind === NodeKind::Modifier;
                $name = $part->kind === NodeKind::DirectVariable ? $file->getText($part) : $name;
            }

            $unused[] = [$name, !$promoted && !array_key_exists($name, $used)];
        }

        // Only the unused parameters after the last used one (all of them when none is used).
        $trailing = [];
        foreach (array_reverse($unused) as [$name, $isUnused]) {
            if (!$isUnused) {
                break;
            }

            $trailing[] = $name;
        }

        $code = count($parameters) === 1 ? 'Found' : 'FoundAfterLastUsed';
        foreach (array_reverse($trailing) as $name) {
            $this->report->issue(
                $context,
                Issue::new("The method parameter {$name} is never used", $keyword->span, 'unused parameter')->withHelp(
                    'Remove the parameter, or use it.',
                ),
                [self::SNIFF . ".{$code}"],
            );
        }
    }

    /**
     * A method of a class (the outermost one, as phpcs's getCondition() finds) that extends or
     * implements something (WordPress-Extra excludes those codes), or a magic method PHP fixes
     * the signature of.
     */
    private function isSkippedMethod(SourceFile $file, Node $function): bool
    {
        if (!in_array($function->kind, [NodeKind::Function, NodeKind::Method], strict: true)) {
            return false;
        }

        $classes = array_values(array_filter(
            $file->getAncestors($function),
            static fn(Node $node): bool => $node->kind === NodeKind::Class_,
        ));
        $class = $classes[count($classes) - 1] ?? null;
        if ($class === null) {
            return false;
        }

        $name = null;
        foreach ($file->getChildren($function) as $child) {
            $name ??= $child->kind === NodeKind::LocalIdentifier ? $child : null;
        }

        if ($name !== null && in_array(strtolower($file->getText($name)), self::MAGIC_METHODS, strict: true)) {
            return true;
        }

        foreach ($file->getChildren($class) as $child) {
            if ($child->kind === NodeKind::Extends || $child->kind === NodeKind::Implements) {
                return true;
            }
        }

        return false;
    }

    /**
     * The variables the body mentions, or NULL when the body holds no code. `${name}` counts
     * as `$name`, inside strings too, as the sniff reads it.
     *
     * @return null|array<string, true>
     */
    private static function usedVariables(SourceFile $file, Node $body): ?array
    {
        $tokens = array_values(array_filter(
            array_slice(PhpToken::tokenize('<?php ' . $file->getText($body)), offset: 1),
            static fn(PhpToken $token): bool => !$token->isIgnorable(),
        ));
        if ($body->kind === NodeKind::Block) {
            $tokens = array_slice($tokens, offset: 1, length: -1);
        }

        if ($tokens === []) {
            return null;
        }

        $used = [];
        foreach ($tokens as $i => $token) {
            $next = $tokens[$i + 1] ?? null;
            if ($token->id === T_VARIABLE) {
                $used[$token->text] = true;
            } elseif ($token->id === T_DOLLAR_OPEN_CURLY_BRACES && $next?->id === T_STRING_VARNAME) {
                $used['$' . $next->text] = true;
            } elseif ($token->text === '$' && $next?->text === '{' && ($tokens[$i + 2] ?? null)?->id === T_STRING) {
                $used['$' . $tokens[$i + 2]->text] = true;
            }
        }

        return $used;
    }
}
