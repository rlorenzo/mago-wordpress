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

use function count;
use function sprintf;
use function strlen;
use function substr;

/**
 * Ports `Squiz.WhiteSpace.ScopeKeywordSpacing`: a single space after `public`, `private`,
 * `protected`, `static`, `final`, `abstract` and `readonly` (`Incorrect`).
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ScopeKeywordSpacingRule implements Rule
{
    private const KEYWORDS = [
        'T_PUBLIC' => true,
        'T_PRIVATE' => true,
        'T_PROTECTED' => true,
        'T_STATIC' => true,
        'T_FINAL' => true,
        'T_ABSTRACT' => true,
        'T_READONLY' => true,
        'T_PUBLIC_SET' => true, // PHP 8.4 `public(set)`
        'T_PROTECTED_SET' => true,
        'T_PRIVATE_SET' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/scope-keyword-spacing',
            name: 'Scope keyword spacing',
            description: 'Requires a single space after a scope keyword or method modifier.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if (!isset(self::KEYWORDS[$token->code])) {
                continue;
            }

            $end = $i;
            $nextToken = PhpcsTokenStream::next($tokens, $end);
            $nextNonWhitespace = $end + 1;
            while (Spacing::isWs($tokens, $nextNonWhitespace)) {
                $nextNonWhitespace++;
            }

            if (!isset($tokens[$nextNonWhitespace]) || $this->isExempt($tokens, $i, $token, $nextToken)) {
                continue;
            }

            $spacing = Spacing::gap($context->file->contents, $tokens, $end, true);
            if ($spacing === 1) {
                continue;
            }

            // The whitespace token after the keyword is the whole gap, line breaks included.
            $ws = Spacing::isWs($tokens, $end + 1) ? $tokens[$end + 1] : null;
            $text = substr(
                $context->file->contents,
                $token->pos,
                $tokens[$end]->pos + strlen($tokens[$end]->text) - $token->pos,
            );
            Spacing::report(
                $this->report,
                $context,
                $token,
                sprintf('Scope keyword "%s" must be followed by a single space; found %s', $text, $spacing),
                ['Squiz.WhiteSpace.ScopeKeywordSpacing.Incorrect'],
                [
                    $ws === null
                        ? TextEdit::insert($tokens[$end]->pos + strlen($tokens[$end]->text), ' ')
                        : Spacing::replace($ws, ' '),
                ],
            );
        }
    }

    /**
     * The sniff's early returns.
     *
     * @param list<PhpcsToken> $tokens
     */
    private function isExempt(array $tokens, int $i, PhpcsToken $token, ?int $nextToken): bool
    {
        $prevToken = PhpcsTokenStream::previous($tokens, $i);
        $prevCode = $prevToken === null ? null : $tokens[$prevToken]->code;
        if ($token->code === 'T_STATIC' && $this->isStaticUse($tokens, $prevToken, $prevCode, $nextToken)) {
            return true;
        }

        if ($prevCode === 'T_AS') {
            return true;
        }

        if (
            $nextToken !== null
            && $tokens[$nextToken]->code === 'T_VARIABLE'
            && !$this->inFunctionDeclaration($tokens, $i)
        ) {
            // A property list spread over several lines is left to other sniffs.
            $end = $this->findNext($tokens, 'T_SEMICOLON', $nextToken + 1, null);
            if ($end === null) {
                return true;
            }

            $multi = $this->findNext($tokens, 'T_VARIABLE', $nextToken + 1, $end);

            return (
                $multi !== null
                && $token->line !== $tokens[$nextToken]->line
                && $tokens[$nextToken]->line !== $tokens[$end]->line
            );
        }

        return false;
    }

    /**
     * `static::`, `new static`, and `static` in a type.
     *
     * @param list<PhpcsToken> $tokens
     */
    private function isStaticUse(array $tokens, ?int $prevToken, ?string $prevCode, ?int $nextToken): bool
    {
        // phpcs does not make `static` after `instanceof` a T_STATIC.
        if (
            $nextToken === null
            || $tokens[$nextToken]->code === 'T_DOUBLE_COLON'
            || $prevCode === 'T_NEW'
            || $prevCode === 'T_INSTANCEOF'
        ) {
            return true;
        }

        if ($prevCode === 'T_BITWISE_OR' || $prevCode === 'T_NULLABLE') {
            return true;
        }

        if ($prevCode === 'T_COLON' && $prevToken !== null) {
            $prevPrev = PhpcsTokenStream::previous($tokens, $prevToken);

            return $prevPrev !== null && $tokens[$prevPrev]->code === 'T_CLOSE_PARENTHESIS';
        }

        return false;
    }

    /**
     * Whether the innermost parentheses around the token are the parameters of a function.
     *
     * @param list<PhpcsToken> $tokens
     */
    private function inFunctionDeclaration(array $tokens, int $i): bool
    {
        if ($tokens[$i]->parens === 0) {
            return false;
        }

        for ($k = $i - 1; $k >= 0; $k--) {
            $code = $tokens[$k]->code;
            if ($code === 'T_CLOSE_PARENTHESIS' && $tokens[$k]->opener !== null) {
                $k = $tokens[$k]->opener;
            } elseif ($code === 'T_OPEN_PARENTHESIS') {
                return Spacing::isFunctionParenthesis($tokens, $k);
            }
        }

        return false;
    }

    /** @param list<PhpcsToken> $tokens */
    private function findNext(array $tokens, string $code, int $from, ?int $end): ?int
    {
        $end ??= count($tokens);
        for ($k = $from; $k < $end; $k++) {
            if ($tokens[$k]->code === $code) {
                return $k;
            }
        }

        return null;
    }
}
