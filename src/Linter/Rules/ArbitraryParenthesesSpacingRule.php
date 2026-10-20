<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function in_array;
use function sprintf;

/**
 * Ports `Generic.WhiteSpace.ArbitraryParenthesesSpacing` as WordPress-Core sets it
 * (`spacing` 1, `ignoreNewlines` true): one space inside the parentheses that are not a
 * function or language-construct call, a function declaration or a control structure
 * (`$x = ( $a + $b )`, `return ( $a )`, `! ( $a )`), and a warning for an empty pair.
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ArbitraryParenthesesSpacingRule implements Rule
{
    private const SNIFF = 'Generic.WhiteSpace.ArbitraryParenthesesSpacing';

    /** Tokens that own the parentheses after them (`Tokens::$parenthesisOpeners` and `fn`). */
    private const OWNERS = [
        'T_ARRAY',
        'T_LIST',
        'T_FUNCTION',
        'T_CLOSURE',
        'T_ANON_CLASS',
        'T_WHILE',
        'T_FOR',
        'T_FOREACH',
        'T_SWITCH',
        'T_IF',
        'T_ELSEIF',
        'T_CATCH',
        'T_DECLARE',
        'T_MATCH',
        'T_FN',
    ];

    /** Tokens before a `(` that make it a call (`Tokens::$functionNameTokens` and others). */
    private const CALLS = [
        'T_STRING',
        'T_EVAL',
        'T_EXIT',
        'T_INCLUDE',
        'T_INCLUDE_ONCE',
        'T_REQUIRE',
        'T_REQUIRE_ONCE',
        'T_ISSET',
        'T_UNSET',
        'T_EMPTY',
        'T_SELF',
        'T_PARENT',
        'T_STATIC',
        'T_VARIABLE',
        'T_CLOSE_PARENTHESIS',
        'T_CLOSE_SQUARE_BRACKET',
        'T_CLOSE_SHORT_ARRAY',
        'T_USE',
        'T_THROW',
        'T_YIELD',
        'T_YIELD_FROM',
        'T_CLONE',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/arbitrary-parentheses-spacing',
            name: 'Arbitrary parentheses spacing',
            description: 'Requires one space inside parentheses that are not a call, declaration or control structure, and reports empty ones.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        $source = $context->file->contents;
        foreach ($tokens as $index => $token) {
            $open = match ($token->code) {
                'T_OPEN_PARENTHESIS' => $token->closer !== null ? $index : null,
                'T_CLOSE_PARENTHESIS' => $token->opener,
                default => null,
            };
            if ($open === null || !$this->isArbitrary($tokens, $open)) {
                continue;
            }

            if ($token->code === 'T_OPEN_PARENTHESIS') {
                $this->checkOpen($context, $source, $tokens, $index);
            } else {
                $this->checkClose($context, $source, $tokens, $index);
            }
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function isArbitrary(array $tokens, int $open): bool
    {
        $before = PhpcsTokenStream::previous($tokens, $open);
        if ($before === null) {
            return true;
        }

        $code = $tokens[$before]->code;
        if (in_array($code, self::OWNERS, strict: true)) {
            return false;
        }

        if ($code === 'T_CLOSE_CURLY_BRACKET') {
            return $this->closesScope($tokens, $before);
        }

        return !in_array($code, self::CALLS, strict: true);
    }

    /**
     * Whether a `}` ends a scope (control structure, function, class), as phpcs's `scope_condition` is set.
     *
     * @param list<PhpcsToken> $tokens
     */
    private function closesScope(array $tokens, int $close): bool
    {
        $open = $tokens[$close]->opener;
        $before = $open === null ? null : PhpcsTokenStream::previous($tokens, $open);
        if ($before === null) {
            return false;
        }

        if (in_array(
            $tokens[$before]->code,
            ['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON', 'T_DOLLAR'],
            strict: true,
        )) {
            return false; // `$o->{$name}()`: the braces hold a name, not a scope
        }

        if (in_array($tokens[$before]->code, ['T_ELSE', 'T_DO', 'T_TRY', 'T_FINALLY'], strict: true)) {
            return true;
        }

        // `) {`, `): type {`: a control structure, function or closure; `{` alone is a plain block.
        return (
            $tokens[$before]->code !== 'T_SEMICOLON'
            && $tokens[$before]->code !== 'T_OPEN_CURLY_BRACKET'
            && $tokens[$before]->code !== 'T_CLOSE_CURLY_BRACKET'
            && $tokens[$before]->code !== 'T_OPEN_TAG'
        );
    }

    /** @param list<PhpcsToken> $tokens */
    private static function isEmpty(array $tokens, int $open): bool
    {
        $k = $open + 1;
        while (Spacing::isWs($tokens, $k)) {
            $k++;
        }

        return $k === $tokens[$open]->closer;
    }

    /** @param list<PhpcsToken> $tokens */
    private function checkOpen(LintContext $context, string $source, array $tokens, int $i): void
    {
        if (self::isEmpty($tokens, $i)) {
            Spacing::report(
                $this->report,
                $context,
                $tokens[$i],
                'Empty set of arbitrary parentheses found.',
                [self::SNIFF . '.FoundEmpty'],
            );

            return;
        }

        if (!isset($tokens[$i + 1], $tokens[$i + 2])) {
            return;
        }

        $inside = Spacing::gap($source, $tokens, $i, after: true);
        if ($inside === 1 || $inside === 'newline') {
            return;
        }

        $edit = $inside === 0 ? TextEdit::insert($tokens[$i]->pos + 1, ' ') : Spacing::replace($tokens[$i + 1], ' ');
        Spacing::report(
            $this->report,
            $context,
            $tokens[$i],
            sprintf('Expected 1 space after open parenthesis; %s found', $inside),
            [self::SNIFF . '.SpaceAfterOpen'],
            [$edit],
        );
    }

    /** @param list<PhpcsToken> $tokens */
    private function checkClose(LintContext $context, string $source, array $tokens, int $i): void
    {
        if ($i < 2 || self::isEmpty($tokens, $tokens[$i]->opener ?? $i)) {
            return;
        }

        $inside = Spacing::gap($source, $tokens, $i, after: false);
        if ($inside === 1 || $inside === 'newline') {
            return;
        }

        $edit = $inside === 0 ? TextEdit::insert($tokens[$i]->pos, ' ') : Spacing::replace($tokens[$i - 1], ' ');
        Spacing::report(
            $this->report,
            $context,
            $tokens[$i],
            sprintf('Expected 1 space before close parenthesis; %s found', $inside),
            [self::SNIFF . '.SpaceBeforeClose'],
            [$edit],
        );
    }
}
