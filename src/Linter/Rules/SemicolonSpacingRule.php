<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function array_pop;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;

/**
 * Ports `Squiz.WhiteSpace.SemicolonSpacing`: no whitespace or comment between a statement and
 * its `;`, other than in an empty statement or the empty parts of a `for ( ; ; )`.
 * @mago-expect lint:cyclomatic-complexity
 */
final class SemicolonSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/semicolon-spacing',
            name: 'Semicolon spacing',
            description: 'Disallows whitespace before a semicolon.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $t = Spacing::tokens($context);
        /** @var list<int> $parens */
        $parens = [];
        foreach ($t as $i => $token) {
            if ($token->code === 'T_OPEN_PARENTHESIS' && $token->closer !== null) {
                $parens[] = $i;
            } elseif ($token->code === 'T_CLOSE_PARENTHESIS' && $token->opener !== null) {
                array_pop($parens);
            } elseif ($token->code === 'T_SEMICOLON' && $i > 0 && Spacing::isEmpty($t[$i - 1])) {
                $this->check($context, $t, $i, $parens === [] ? null : $parens[count($parens) - 1]);
            }
        }
    }

    /**
     * @param list<PhpcsToken> $t
     */
    private function check(LintContext $context, array $t, int $i, ?int $paren): void
    {
        $forCondition = false;
        if ($paren !== null) {
            $owner = $paren - 1;
            while ($owner > 0 && Spacing::isEmpty($t[$owner])) {
                --$owner;
            }

            $forCondition = $t[$owner]->code === 'T_FOR';
        }

        $k = $i - 2;
        while ($k >= 0 && ($forCondition ? $t[$k]->code === 'T_WHITESPACE' : Spacing::isEmpty($t[$k]))) {
            --$k;
        }

        if ($k < 0) {
            return;
        }

        $nonSpace = $t[$k];
        if (
            $nonSpace->code === 'T_SEMICOLON'
            || $forCondition && $k === $paren
            || $nonSpace->code === 'T_COLON'
            || $nonSpace->code === 'T_OPEN_CURLY_BRACKET' && $this->opensScope($t, $k)
        ) {
            return;
        }

        $found = '';
        for ($j = $k; $j < $i; $j++) {
            $found .= $t[$j]->text;
        }

        $found = str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $found . ';');
        $semicolon = $t[$i];
        $edits = [];
        if ($t[$i - 1]->code === 'T_WHITESPACE' && ($i - 1) > $k) {
            // phpcs's line comment holds its own line break, so only the whitespace after it goes.
            $ws = $t[$i - 1];
            $keep = 0;
            if ($i >= 2 && Spacing::isLineComment($t[$i - 2])) {
                $keep = str_starts_with($ws->text, "\r\n") ? 2 : (str_starts_with($ws->text, "\n") ? 1 : 0);
            }
            $edits[] = TextEdit::replace(new Span($ws->pos + $keep, $ws->pos + strlen($ws->text)), '');
        }

        $edits[] = TextEdit::insert($nonSpace->pos + strlen($nonSpace->text), ';');
        $edits[] = Spacing::replace($semicolon, '');
        Spacing::report(
            $this->report,
            $context,
            $semicolon,
            sprintf('Space found before semicolon; expected "%s" but found "%s"', $nonSpace->text . ';', $found),
            ['Squiz.WhiteSpace.SemicolonSpacing.Incorrect'],
            $edits,
        );
    }

    /**
     * Whether a `{` opens a scope (control structure, function, class) rather than a plain block.
     *
     * @param list<PhpcsToken> $t
     */
    private function opensScope(array $t, int $open): bool
    {
        for ($k = $open - 1; $k >= 0; $k--) {
            if (!Spacing::isEmpty($t[$k])) {
                return !in_array(
                    $t[$k]->code,
                    ['T_SEMICOLON', 'T_OPEN_CURLY_BRACKET', 'T_CLOSE_CURLY_BRACKET', 'T_OPEN_TAG'],
                    strict: true,
                );
            }
        }

        return false;
    }
}
