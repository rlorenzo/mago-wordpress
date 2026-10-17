<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;
use function ltrim;
use function max;
use function min;
use function preg_match;
use function strlen;
use function strtolower;
use function substr;

/**
 * Ports `Generic.PHP.LowerCaseConstant`: `TRUE`, `FALSE` and `NULL` (and `\NULL`) written in
 * anything but lowercase. The same words inside a type declaration are `LowerCaseType`'s, and
 * a class named `True` after `new` is a class name, not the constant; Mago parses it as the
 * constant, so it is skipped by the `new` before it.
 */
final class LowercaseConstantRule implements Rule
{
    private const WORDS = ['true', 'false', 'null'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/lowercase-constant',
            name: 'Lowercase constant',
            description: 'Reports `TRUE`, `FALSE` and `NULL` that are not written in lowercase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Literal, NodeKind::ConstantAccess],
        );
    }

    public function lint(LintContext $context): void
    {
        $node = $context->node;
        $text = $context->file->getText($node);
        $offset = $node->span->start;
        if ($node->kind === NodeKind::ConstantAccess) {
            $slash = strlen($text) - strlen(ltrim($text, '\\'));
            if ($slash !== 1) {
                return;
            }

            $text = substr($text, 1);
            ++$offset;
        }

        $lower = strtolower($text);
        if (
            $text === $lower
            || !in_array($lower, self::WORDS, strict: true)
            || self::followsNew($context, $node->span->start)
        ) {
            return;
        }

        $span = new Span($offset, $offset + strlen($text));
        $this->report->issue(
            $context,
            Issue::new(
                'TRUE, FALSE and NULL must be lowercase; expected "' . $lower . '" but found "' . $text . '"',
                $span,
                'not lowercase',
            )->withEdit(TextEdit::replace($span, $lower)),
            ['Generic.PHP.LowerCaseConstant.Found'],
        );
    }

    private static function followsNew(LintContext $context, int $start): bool
    {
        return preg_match('/\bnew\s*$/i', substr($context->file->contents, max(0, $start - 12), min(12, $start))) === 1;
    }
}
