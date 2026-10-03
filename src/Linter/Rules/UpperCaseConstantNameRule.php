<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;

use function ltrim;
use function preg_match;
use function strlen;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * Ports `Generic.NamingConventions.UpperCaseConstantName`: a `const` whose name is not
 * uppercase (`ClassConstantNotUpperCase`, for global constants too, as the sniff words it),
 * and a `define()` whose first argument is a string literal naming one
 * (`ConstantNotUpperCase`). Like the sniff, only the first constant of a `const` list is
 * checked, a `define()` name is compared with its quotes (and after its last `\`), and a
 * named or non-literal first argument is skipped.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class UpperCaseConstantNameRule implements Rule
{
    private const SNIFF = 'Generic.NamingConventions.UpperCaseConstantName';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/upper-case-constant-name',
            name: 'Upper case constant name',
            description: 'Reports a constant, declared with `const` or `define()`, whose name is not all uppercase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Constant, NodeKind::ClassLikeConstant, NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $node = $context->node;
        if ($node->kind === NodeKind::FunctionCall) {
            $this->define($context, $node);

            return;
        }

        $file = $context->file;
        foreach ($file->getChildren($node) as $item) {
            if ($item->kind !== NodeKind::ConstantItem && $item->kind !== NodeKind::ClassLikeConstantItem) {
                continue;
            }

            $name = $file->getChildren($item)[0] ?? null;
            $text = $name === null ? '' : $file->getText($name);
            if ($name !== null && strtoupper($text) !== $text) {
                $upper = strtoupper($text);
                $this->report->issue(
                    $context,
                    Issue::new(
                        "Class constants must be uppercase; expected {$upper} but found {$text}",
                        $name->span,
                        'not uppercase',
                    )->withHelp("Rename it to `{$upper}`."),
                    [self::SNIFF . '.ClassConstantNotUpperCase'],
                );
            }

            return;
        }
    }

    private function define(LintContext $context, Node $call): void
    {
        $file = $context->file;
        $callee = Calls::name($file, $call);
        $slash = $callee === null ? false : strrpos($callee, '\\');
        if ($callee === null || strtolower($slash === false ? $callee : substr($callee, $slash + 1)) !== 'define') {
            return;
        }

        $arguments = $file->getChildren($call)[1] ?? null;
        if ($arguments === null) {
            return;
        }

        $text = $file->getText($arguments);
        // The first token after `(`, past whitespace and comments.
        $inner = ltrim(substr($text, 1));
        while (preg_match('~^(?:/\*.*?\*/|(?://|#)[^\n]*)~s', $inner, $comment) === 1) {
            $inner = ltrim(substr($inner, strlen($comment[0])));
        }

        if (preg_match('/^(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\$]|\\\\.)*")/s', $inner, $match) !== 1) {
            return;
        }

        $literal = $match[1];
        $split = strrpos($literal, '\\');
        $prefix = $split === false ? '' : substr($literal, 0, $split + 1);
        $name = $split === false ? $literal : substr($literal, $split + 1);
        if (strtoupper($name) === $name) {
            return;
        }

        $start = $arguments->span->start + strlen($text) - strlen($inner);
        $expected = $prefix . strtoupper($name);
        $this->report->issue(
            $context,
            Issue::new(
                "Constants must be uppercase; expected {$expected} but found {$prefix}{$name}",
                new Span($start, $start + strlen($literal)),
                'not uppercase',
            )->withHelp('Use an uppercase constant name.'),
            [self::SNIFF . '.ConstantNotUpperCase'],
        );
    }
}
