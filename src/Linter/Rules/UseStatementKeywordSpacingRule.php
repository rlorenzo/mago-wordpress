<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SpacesFixer;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function count;
use function strlen;
use function strtolower;
use function ucfirst;

/**
 * Ports `Universal.UseStatements.KeywordSpacing` (`SpaceBeforeAs`, `SpaceAfterAs`,
 * `SpaceAfterConst`, `SpaceAfterFunction`): exactly one space around `as` and after `function` or
 * `const` in an import `use` statement, at the start or inside a group. The spacing after `use`
 * itself is `generic/language-construct-spacing`'s, as WordPress-Core turns `SpaceAfterUse` off.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class UseStatementKeywordSpacingRule implements Rule
{
    private const SNIFF = 'Universal.UseStatements.KeywordSpacing';

    private const OO = [
        'T_CLASS' => true,
        'T_TRAIT' => true,
        'T_INTERFACE' => true,
        'T_ENUM' => true,
        'T_ANON_CLASS' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/use-statement-keyword-spacing',
            name: 'Use statement keyword spacing',
            description: 'Requires one space around `as` and after `function`/`const` in an import use statement.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (isset(self::OO[$token->code])) {
                $i = $token->scopeCloser ?? $i; // a trait `use` is not an import
                continue;
            }

            if ($token->code !== 'T_USE') {
                continue;
            }

            $prev = PhpcsTokenStream::previous($tokens, $i);
            if ($prev !== null && $tokens[$prev]->code === 'T_CLOSE_PARENTHESIS') {
                continue; // a closure `use`
            }

            $this->statement($context, $tokens, $i);
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function statement(LintContext $context, array $tokens, int $use): void
    {
        $end = null;
        $group = null;
        $count = count($tokens);
        for ($k = $use + 1; $k < $count; $k++) {
            $code = $tokens[$k]->code;
            if ($code === 'T_SEMICOLON' || $code === 'T_CLOSE_TAG') {
                $end = $k;
                break;
            }

            if ($group === null && $code === 'T_OPEN_CURLY_BRACKET') {
                // The `{` of a group follows the `\`, whitespace and comments between them aside.
                $before = PhpcsTokenStream::previous($tokens, $k);
                if ($before !== null && $tokens[$before]->code === 'T_NS_SEPARATOR') {
                    $group = $k;
                }
            }
        }

        if ($end === null) {
            return;
        }

        for ($k = $use + 1; $k < $end; $k++) {
            if ($tokens[$k]->code === 'T_AS' && Spacing::isEmpty($tokens[$k - 1])) {
                $this->before($context, $tokens, $k);
                $this->after($context, $tokens, $k);
            }
        }

        $first = PhpcsTokenStream::next($tokens, $use);
        if ($first !== null && self::isKeyword($tokens[$first])) {
            $this->after($context, $tokens, $first);

            return;
        }

        $close = $group === null ? null : $tokens[$group]->closer;
        if ($group === null || $close === null) {
            return;
        }

        $current = $group;
        do {
            $current = PhpcsTokenStream::next($tokens, $current, $close);
            if ($current === null) {
                return;
            }

            if (self::isKeyword($tokens[$current])) {
                $this->after($context, $tokens, $current);
            }

            $current = $this->comma($tokens, $current + 1, $close);
        } while ($current !== null);
    }

    /** @param list<PhpcsToken> $tokens */
    private function comma(array $tokens, int $from, int $close): ?int
    {
        for ($k = $from; $k < $close; $k++) {
            if ($tokens[$k]->code === 'T_COMMA') {
                return $k;
            }
        }

        return null;
    }

    private static function isKeyword(PhpcsToken $token): bool
    {
        $lower = strtolower($token->text);

        return $lower === 'const' || $lower === 'function';
    }

    /** @param list<PhpcsToken> $tokens */
    private function before(LintContext $context, array $tokens, int $at): void
    {
        $prev = PhpcsTokenStream::previous($tokens, $at);
        if ($prev === null) {
            return;
        }

        $lower = strtolower($tokens[$at]->text);
        SpacesFixer::check(
            $context,
            $this->report,
            $tokens[$prev]->pos + strlen($tokens[$prev]->text),
            $tokens[$at]->pos,
            1,
            'Expected %1$s before the "' . $lower . '" keyword. Found: %2$s',
            self::SNIFF . '.SpaceBefore' . ucfirst($lower),
            Spacing::span($tokens[$at]),
        );
    }

    /** @param list<PhpcsToken> $tokens */
    private function after(LintContext $context, array $tokens, int $at): void
    {
        $next = PhpcsTokenStream::next($tokens, $at);
        if ($next === null) {
            return;
        }

        $lower = strtolower($tokens[$at]->text);
        SpacesFixer::check(
            $context,
            $this->report,
            $tokens[$at]->pos + strlen($tokens[$at]->text),
            $tokens[$next]->pos,
            1,
            'Expected %1$s after the "' . $lower . '" keyword. Found: %2$s',
            self::SNIFF . '.SpaceAfter' . ucfirst($lower),
            Spacing::span($tokens[$at]),
        );
    }
}
