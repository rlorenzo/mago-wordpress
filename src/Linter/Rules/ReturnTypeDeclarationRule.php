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

use function in_array;
use function strlen;

/**
 * Ports `PSR12.Functions.ReturnTypeDeclaration`: no space between the closing parenthesis and the
 * colon (`SpaceBeforeColon`), exactly one space between the colon and the return type
 * (`SpaceBeforeReturnType`), for functions, methods, closures and arrow functions.
 * @mago-expect lint:cyclomatic-complexity
 */
final class ReturnTypeDeclarationRule implements Rule
{
    private const SNIFF = 'PSR12.Functions.ReturnTypeDeclaration';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/return-type-declaration',
            name: 'Return type declaration',
            description: 'Reports a space between the closing parenthesis and the colon of a return type, and anything but one space between the colon and the type.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        foreach ($tokens as $index => $token) {
            if (!in_array($token->code, ['T_FUNCTION', 'T_CLOSURE', 'T_FN'], strict: true)) {
                continue;
            }

            $parentheses = FunctionTokens::parentheses($tokens, $index);
            if ($parentheses === null) {
                continue;
            }

            $close = $parentheses[1];
            if ($token->code === 'T_CLOSURE') {
                $use = FunctionTokens::useClause($tokens, $close, $token->scopeOpener);
                $close = $use === null ? $close : $use[2];
            }

            $colon = PhpcsTokenStream::next($tokens, $close);
            if ($colon === null || !in_array($tokens[$colon]->code, ['T_COLON', 'T_INLINE_ELSE'], strict: true)) {
                continue;
            }

            $type = PhpcsTokenStream::next($tokens, $colon);
            if ($type === null) {
                continue;
            }

            $this->afterColon($context, $tokens, $colon, $type);
            $this->beforeColon($context, $tokens, $close, $colon);
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function afterColon(LintContext $context, array $tokens, int $colon, int $type): void
    {
        $at = $tokens[$type];
        $issue = Issue::new(
            'There must be a single space between the colon and type in a return type declaration',
            new Span($at->pos, $at->pos + strlen($at->text)),
        );
        $gap = $type - $colon;
        if ($gap === 1) {
            $issue = $issue->withEdit(TextEdit::insert($at->pos, ' '));
        } elseif ($gap === 2 && $tokens[$colon + 1]->code === 'T_WHITESPACE') {
            $space = $tokens[$colon + 1];
            if ($space->text === ' ') {
                return;
            }

            // phpcs splits the run at each line break: only a lone one, or none, leaves the colon next to it.
            if (!FunctionTokens::hasNewline($space) || in_array($space->text, ["\n", "\r\n"], strict: true)) {
                $issue = $issue->withEdit(TextEdit::replace(
                    new Span($space->pos, $space->pos + strlen($space->text)),
                    ' ',
                ));
            }
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.SpaceBeforeReturnType']);
    }

    /** @param list<PhpcsToken> $tokens */
    private function beforeColon(LintContext $context, array $tokens, int $close, int $colon): void
    {
        if (($colon - 1) === $close) {
            return;
        }

        $issue = Issue::new(
            'There must not be a space before the colon in a return type declaration',
            new Span($tokens[$colon]->pos, $tokens[$colon]->pos + 1),
        );
        if (FunctionTokens::previousNonWhitespace($tokens, $colon) === $close) {
            $issue = $issue->withEdit(TextEdit::delete(new Span($tokens[$close]->pos + 1, $tokens[$colon]->pos)));
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.SpaceBeforeColon']);
    }
}
