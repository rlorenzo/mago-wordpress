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

use function preg_match;
use function str_contains;
use function strspn;
use function substr;

/**
 * Ports the `SpaceAfterCloseBrace` part of `Squiz.ControlStructures.ControlSignature`: the
 * space between a closing brace and the `else`, `elseif`, `catch`, `finally` or do-`while` after it must
 * be exactly one space on the same line. `mago format` writes `} else` already, but leaves a
 * comment between the two (`} // note` then `else` on the next line), which phpcs reports
 * and does not fix. The fix, where no comment is in the way, is phpcbf's: one space. The
 * sniff's other codes are spacing `mago format` produces.
 */
final class ControlSignatureRule implements Rule
{
    private const CODE = 'Squiz.ControlStructures.ControlSignature.SpaceAfterCloseBrace';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/control-signature',
            name: 'Control signature',
            description: 'Reports a closing brace not followed by exactly one space before `else`, `elseif`, `catch` or `finally`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The parents, as a linter snapshot holds only the target's own subtree.
            targets: [NodeKind::IfStatementBody, NodeKind::Try, NodeKind::DoWhile],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        foreach ($children as $position => $clause) {
            $keyword = match ($clause->kind) {
                NodeKind::IfStatementBodyElseClause,
                NodeKind::IfStatementBodyElseIfClause,
                NodeKind::TryCatchClause,
                NodeKind::TryFinallyClause,
                    => $file->getChildren($clause)[0] ?? null,
                NodeKind::Keyword => $position > 0 ? $clause : null, // the `while` of a `do`
                default => null,
            };
            $previous = $children[$position - 1] ?? null;
            if ($keyword === null || $previous === null) {
                continue;
            }

            $closer = $previous->span->end - 1;
            if (($file->contents[$closer] ?? '') !== '}') {
                continue; // an unbraced body
            }

            $gap = substr($file->contents, $closer + 1, $keyword->span->start - $closer - 1);
            $found = match (true) {
                $gap === '' || strspn($gap, characters: " \t\r\n") === 0 => '0',
                str_contains($gap, "\n") => 'newline',
                default => (string) strspn($gap, characters: " \t"),
            };
            // phpcs reads only the whitespace token after the brace and counts its length, so
            // `} /* c */ else` and `}<tab>else` have one space and pass; a comment right after the
            // brace (`}/* c */ else`) is a 0 above.
            if ($found === '1') {
                continue;
            }

            $issue = Issue::new(
                "Expected 1 space after closing brace; {$found} found",
                new Span($closer, $closer + 1),
                'closing brace',
            )->withHelp('Put one space between the closing brace and `' . $file->getText($keyword) . '`.');
            if (preg_match('~/\*|//|#~', $gap) !== 1) {
                $issue = $issue->withEdit(TextEdit::replace(new Span($closer + 1, $keyword->span->start), ' '));
            }

            $this->report->issue($context, $issue, [self::CODE]);
        }
    }
}
