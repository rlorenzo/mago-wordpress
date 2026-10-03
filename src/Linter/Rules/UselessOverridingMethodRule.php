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
use function array_map;
use function array_values;
use function count;
use function in_array;
use function preg_replace;
use function str_ends_with;
use function strcasecmp;
use function strtolower;
use function substr;
use function trim;

/**
 * Ports `Generic.CodeAnalysis.UselessOverridingMethod`: a method of a class, anonymous class
 * or trait whose whole body is `parent::sameMethod(...)` (or `return parent::...`) passing its
 * own parameters through unchanged, in order, as the sniff compares them: by text, so a
 * variadic `...$args`, a named argument or a changed default value make it not useless.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class UselessOverridingMethodRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/useless-overriding-method',
            name: 'Useless overriding method',
            description: 'Reports a method whose body only calls the parent method of the same name with the same arguments.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::AnonymousClass, NodeKind::Trait],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getChildren($context->node) as $member) {
            $method = $member->kind === NodeKind::ClassLikeMember ? $file->getChildren($member)[0] ?? null : null;
            if ($method?->kind === NodeKind::Method && self::isUseless($file, $method)) {
                $keyword = self::child($file, $method, NodeKind::Keyword) ?? $method;
                $this->report->issue(
                    $context,
                    Issue::new('Possible useless method overriding detected', $keyword->span)->withHelp(
                        'Remove the method; the parent method runs without it.',
                    ),
                    ['Generic.CodeAnalysis.UselessOverridingMethod.Found'],
                );
            }
        }
    }

    private static function isUseless(SourceFile $file, Node $method): bool
    {
        $name = self::child($file, $method, NodeKind::LocalIdentifier);
        $block = $file->getFirstDescendant(
            self::child($file, $method, NodeKind::MethodBody) ?? $method,
            NodeKind::Block,
        );
        $call = $block === null ? null : self::onlyCall($file, $block);
        if ($name === null || $call === null) {
            return false;
        }

        [$class, $selector, $arguments] = $file->getChildren($call) + [null, null, null];
        if (
            $class === null
            || strtolower(trim($file->getText($class))) !== 'parent'
            || $selector === null
            || strcasecmp(trim($file->getText($selector)), $file->getText($name)) !== 0
            || $arguments === null
        ) {
            return false;
        }

        $signature = [];
        $parameters = self::child($file, $method, NodeKind::FunctionLikeParameterList);
        foreach ($parameters === null ? [] : $file->getChildren($parameters) as $parameter) {
            $variable = $file->getFirstDescendant($parameter, NodeKind::DirectVariable);
            $signature[] = $variable === null ? '' : $file->getText($variable);
        }

        return self::arguments($file->getText($arguments)) === $signature;
    }

    /** The `parent::...()` call that makes up the whole block, with or without `return`. */
    private static function onlyCall(SourceFile $file, Node $block): ?Node
    {
        $statements = array_values(array_filter(
            $file->getChildren($block),
            static fn(Node $statement): bool => !in_array(
                $file->getChildren($statement)[0]->kind ?? null,
                [NodeKind::OpeningTag, NodeKind::ClosingTag],
                strict: true,
            ),
        ));
        // A call without its `;` is a parse error the sniff skips.
        $text = count($statements) === 1
            ? (string) preg_replace(
                '/\?>\s*<\?php$/i',
                replacement: '?>',
                subject: trim($file->getText($statements[0])),
            )
            : '';
        if (!str_ends_with($text, ';') && !str_ends_with($text, '?>')) {
            return null;
        }

        $node = $file->getChildren($statements[0])[0] ?? null;
        while (
            $node !== null
            && in_array(
                $node->kind,
                [
                    NodeKind::Return,
                    NodeKind::ExpressionStatement,
                    NodeKind::Expression,
                    NodeKind::Call,
                ],
                strict: true,
            )
        ) {
            $node = $file->getChildren($node)[0] ?? null;
            if ($node?->kind === NodeKind::Keyword) {
                $node = $file->getChildren($file->getParent($node) ?? $node)[1] ?? null;
            }
        }

        return $node?->kind === NodeKind::StaticMethodCall ? $node : null;
    }

    /**
     * The arguments as the sniff collects them: the tokens between top-level commas, without
     * whitespace or comments; only parentheses nest.
     *
     * @return list<string>
     */
    private static function arguments(string $list): array
    {
        $arguments = [''];
        $depth = 0;
        foreach (PhpToken::tokenize('<?php ' . substr($list, offset: 1, length: -1)) as $index => $token) {
            if ($index === 0 || $token->isIgnorable()) {
                continue;
            }

            // The sniff counts parentheses but leaves them out of the argument text.
            if ($token->text === '(' || $token->text === ')') {
                $depth += $token->text === '(' ? 1 : -1;
                continue;
            }

            if ($depth === 0 && $token->text === ',') {
                $arguments[] = '';
                continue;
            }

            $arguments[count($arguments) - 1] .= $token->text;
        }

        return array_values(array_filter(array_map(trim(...), $arguments), static fn(string $a): bool => $a !== ''));
    }

    private static function child(SourceFile $file, Node $node, NodeKind $kind): ?Node
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === $kind) {
                return $child;
            }
        }

        return null;
    }
}
