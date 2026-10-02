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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;

use function preg_match;
use function preg_replace;
use function rtrim;
use function strtolower;
use function substr;

/**
 * Ports `Universal.CodeAnalysis.NoDoubleNegative`: `!!` (use a `(bool)` cast) and `!!!` or
 * more (one `!` does the same). The fixes are phpcbf's; like phpcbf, `!!` before an
 * `instanceof` (`FoundDoubleWithInstanceof`) is not fixed, and neither, here, is a chain
 * with a comment between the operators.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class NoDoubleNegativeRule implements Rule
{
    private const SNIFF = 'Universal.CodeAnalysis.NoDoubleNegative';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/no-double-negative',
            name: 'No double negative',
            description: 'Reports `!!` (use a `(bool)` cast) and `!!!` or more (use one `!`).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::UnaryPrefix],
        );
    }

    /** @mago-expect lint:halstead */
    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $first = self::notOperator($file, $context->node);
        // Only the outermost `!` of a chain reports.
        if (
            $first === null
            || substr(rtrim(substr($file->contents, offset: 0, length: $first->span->start)), offset: -1) === '!'
        ) {
            return;
        }

        $count = 1;
        $last = $first;
        $operand = $file->getChildren($context->node)[1] ?? null;
        while (($inner = $operand === null ? null : $file->getChildren($operand)[0] ?? null) !== null) {
            $not = self::notOperator($file, $inner);
            if ($not === null) {
                break;
            }

            $count++;
            $last = $not;
            $operand = $file->getChildren($inner)[1] ?? null;
        }

        if ($count === 1) {
            return;
        }

        $chain = substr($file->contents, $first->span->start, $last->span->end - $first->span->start);
        $found = (string) preg_replace('/\s+/', replacement: ' ', subject: $chain);
        $plain = preg_match('~/\*|//|#~', $chain) !== 1;
        $span = new Span($first->span->start, $last->span->end);
        if (($count % 2) === 1) {
            $issue = Issue::new(
                "Triple negative (or more) detected. Use a singular not (!) operator instead. Found: {$found}",
                $span,
                'negates more than once',
            )->withHelp('Use a single `!`.');
            $this->report(
                $context,
                $plain ? $issue->withEdit(TextEdit::delete(new Span($first->span->start, $last->span->start))) : $issue,
                'FoundTriple',
            );

            return;
        }

        // The sniff only looks for `instanceof` when no `(` follows the last `!`.
        $instanceof =
            ($file->contents[$operand?->span->start ?? 0] ?? '') !== '(' && self::isInstanceof($file, $operand);
        $issue = Issue::new(
            'Double negative detected. Use a (bool) cast '
            . ($instanceof ? 'and parentheses around the instanceof expression ' : '')
            . "instead. Found: {$found}",
            $span,
            'double negative',
        )->withHelp('Use a `(bool)` cast.');
        $this->report(
            $context,
            $plain && !$instanceof ? $issue->withEdit(TextEdit::replace($span, '(bool)')) : $issue,
            $instanceof ? 'FoundDoubleWithInstanceof' : 'FoundDouble',
        );
    }

    /** The `!` operator of a unary prefix node, or null for another node or operator. */
    private static function notOperator(SourceFile $file, Node $node): ?Node
    {
        $operator = $node->kind === NodeKind::UnaryPrefix ? $file->getChildren($node)[0] ?? null : null;

        return $operator !== null && $file->getText($operator) === '!' ? $operator : null;
    }

    /** Whether the last `!` applies to an `instanceof` check, which a cast would bind tighter than. */
    private static function isInstanceof(SourceFile $file, ?Node $operand): bool
    {
        $binary = $operand === null ? null : $file->getChildren($operand)[0] ?? null;
        $operator = $binary?->kind === NodeKind::Binary ? $file->getChildren($binary)[1] ?? null : null;

        return $operator !== null && strtolower($file->getText($operator)) === 'instanceof';
    }

    private function report(LintContext $context, Issue $issue, string $code): void
    {
        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }
}
