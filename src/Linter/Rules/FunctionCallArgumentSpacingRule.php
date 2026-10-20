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
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;

use function strlen;

/**
 * Ports `Generic.Functions.FunctionCallArgumentSpacing`: no space before a comma in an argument list
 * (`SpaceBeforeComma`), one space after it (`NoSpaceAfterComma`, `TooMuchSpaceAfterComma`), except
 * when the next argument is on another line. Only the commas of the call itself are read, not those
 * of a nested call, array, closure body, arrow function or `match`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FunctionCallArgumentSpacingRule implements Rule
{
    private const SNIFF = 'Generic.Functions.FunctionCallArgumentSpacing';

    private const NAMES = [
        'T_STRING' => true,
        'T_ISSET' => true,
        'T_UNSET' => true,
        'T_STATIC' => true,
        'T_VARIABLE' => true,
        'T_CLOSE_PARENTHESIS' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
    ];

    private const HEREDOC = [
        'T_START_HEREDOC' => true,
        'T_END_HEREDOC' => true,
        'T_HEREDOC' => true,
        'T_START_NOWDOC' => true,
        'T_END_NOWDOC' => true,
        'T_NOWDOC' => true,
    ];

    /** Tokens that, before a `{`, make its `}` a dynamic name (`$o->{$name}()`) rather than a block end. */
    private const DYNAMIC = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_DOUBLE_COLON' => true,
        'T_DOLLAR' => true,
    ];

    /** Tokens whose body, or whose arguments' commas, are not the call's own. */
    private const SKIPPED = ['T_CLOSURE' => true, 'T_ANON_CLASS' => true, 'T_MATCH' => true, 'T_FN' => true];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/function-call-argument-spacing',
            name: 'Function call argument spacing',
            description: 'Reports a space before a comma in an argument list, and anything but one space after it (unless the next argument is on another line).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        foreach ($tokens as $index => $token) {
            if (!isset(self::NAMES[$token->code])) {
                continue;
            }

            $open = PhpcsTokenStream::next($tokens, $index);
            if ($open === null || $tokens[$open]->code !== 'T_OPEN_PARENTHESIS' || $tokens[$open]->closer === null) {
                continue;
            }

            if (!self::isCall($tokens, $index)) {
                continue;
            }

            $this->check($context, $tokens, $open, $tokens[$open]->closer);
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private static function isCall(array $tokens, int $name): bool
    {
        $before = $name;
        do {
            $before = PhpcsTokenStream::previous($tokens, $before);
        } while ($before !== null && $tokens[$before]->code === 'T_BITWISE_AND');

        if ($before !== null && ($tokens[$before]->code === 'T_FUNCTION' || $tokens[$before]->code === 'T_CLASS')) {
            return false;
        }

        if ($tokens[$name]->code !== 'T_CLOSE_CURLY_BRACKET') {
            return true;
        }

        $opener = $tokens[$name]->opener;
        $prior = $opener === null ? null : PhpcsTokenStream::previous($tokens, $opener);

        return $prior !== null && isset(self::DYNAMIC[$tokens[$prior]->code]);
    }

    /** @param list<PhpcsToken> $tokens */
    private function check(LintContext $context, array $tokens, int $open, int $close): void
    {
        $source = $context->file->contents;
        for ($i = $open + 1; $i < $close; $i++) {
            $token = $tokens[$i];
            if (isset(self::SKIPPED[$token->code])) {
                if ($token->scopeCloser === null) {
                    continue;
                }

                $i = $token->scopeCloser;
            } elseif ($token->closer !== null) {
                $i = $token->closer;
            } elseif ($token->code === 'T_COMMA') {
                $this->comma($context, $tokens, $i, $close, $source);
            }
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function comma(LintContext $context, array $tokens, int $comma, int $close, string $source): void
    {
        $at = $tokens[$comma];
        $span = new Span($at->pos, $at->pos + 1);
        $before = $tokens[$comma - 1];
        if ($before->code === 'T_WHITESPACE') {
            $prev = PhpcsTokenStream::previous($tokens, $comma - 1);
            if ($prev !== null && !isset(self::HEREDOC[$tokens[$prev]->code])) {
                $issue = Issue::new('Space found before comma in argument list', $span);
                $issue = $tokens[$prev]->line !== $at->line
                    ? $issue
                        ->withEdit(TextEdit::insert($tokens[$prev]->pos + strlen($tokens[$prev]->text), ','))
                        ->withEdit(TextEdit::delete($span))
                    : $issue->withEdit(TextEdit::delete(new Span($before->pos, $before->pos + strlen($before->text))));
                $this->report->issue($context, $issue, [self::SNIFF . '.SpaceBeforeComma']);
            }
        }

        $after = $tokens[$comma + 1];
        if ($after->code !== 'T_WHITESPACE') {
            // A trailing comma after the last argument is outside the scope of this sniff.
            if (($comma + 1) !== $close) {
                $this->report->issue(
                    $context,
                    Issue::new('No space found after comma in argument list', $span)->withEdit(TextEdit::insert(
                        $at->pos + 1,
                        ' ',
                    )),
                    [self::SNIFF . '.NoSpaceAfterComma'],
                );
            }

            return;
        }

        // A line break in the space means one argument per line, which is valid.
        $next = PhpcsTokenStream::next($tokens, $comma);
        if ($next === null || $tokens[$next]->line !== $at->line || FunctionTokens::hasNewline($after)) {
            return;
        }

        $space = FunctionTokens::width($after, $source);
        if ($space > 1) {
            $this->report->issue(
                $context,
                Issue::new(
                    "Expected 1 space after comma in argument list; {$space} found",
                    $span,
                )->withEdit(TextEdit::replace(new Span($after->pos, $after->pos + strlen($after->text)), ' ')),
                [self::SNIFF . '.TooMuchSpaceAfterComma'],
            );
        }
    }
}
