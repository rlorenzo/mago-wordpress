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
use Rlorenzo\MagoWordPress\Internal\FunctionTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function strlen;

/**
 * Ports `PSR2.Methods.FunctionClosingBrace`: the closing brace of a function or closure follows
 * the last line of its body directly, with no blank line before it (`SpacingBeforeClose`). A brace
 * on the line of the last statement is left to other sniffs.
 */
final class FunctionClosingBraceRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/function-closing-brace',
            name: 'Function closing brace',
            description: 'Reports blank lines between the last line of a function body and its closing brace.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        foreach ($tokens as $token) {
            if ($token->code !== 'T_FUNCTION' && $token->code !== 'T_CLOSURE' || $token->scopeCloser === null) {
                continue;
            }

            $close = $token->scopeCloser;
            $prev = FunctionTokens::previousNonWhitespace($tokens, $close);
            if ($prev === null) {
                continue;
            }

            $found = $tokens[$close]->line - FunctionTokens::endLine($tokens[$prev]) - 1;
            if ($found <= 0) {
                continue;
            }

            $issue = Issue::new(
                "Function closing brace must go on the next line following the body; found {$found} blank lines before brace",
                new Span($tokens[$close]->pos, $tokens[$close]->pos + 1),
            );
            $between = $tokens[$close - 1];
            if ($between->code === 'T_WHITESPACE') {
                $issue = $issue->withEdit(TextEdit::replace(
                    new Span($between->pos, $between->pos + strlen($between->text)),
                    FunctionTokens::collapseBlankLines($between->text),
                ));
            }

            $this->report->issue($context, $issue, ['PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose']);
        }
    }
}
