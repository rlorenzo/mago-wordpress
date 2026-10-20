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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function count;
use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function substr_count;

/**
 * Ports `PSR2.Namespaces.NamespaceDeclaration` (`BlankLineAfter`): exactly one blank line after
 * a `namespace` declaration, or after the closing brace of a braced one. Fixed as phpcbf does.
 * Where phpcs counts tokens, a line break ends one: the "token after the declaration's line" is
 * found from the shared token stream's whitespace.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class NamespaceDeclarationRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/namespace-declaration',
            name: 'Namespace declaration',
            description: 'Requires exactly one blank line after a namespace declaration.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if ($token->code !== 'T_NAMESPACE') {
                continue;
            }

            $next = PhpcsTokenStream::next($tokens, $i);
            if ($next !== null && $tokens[$next]->code === 'T_NS_SEPARATOR') {
                continue; // `namespace\foo()`, the keyword as an operator
            }

            $prev = PhpcsTokenStream::previous($tokens, $i);
            if ($prev !== null && $tokens[$prev]->code === 'T_CASE') {
                continue; // an enum case named Namespace
            }

            $this->check($context, $tokens, $i);
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function check(LintContext $context, array $tokens, int $namespace): void
    {
        $end = self::end($tokens, $namespace);
        $count = count($tokens);
        $line = $tokens[$end]->line;
        $at = null; // where the first token of the next line starts
        $text = ''; // that token's text when it is whitespace: its line breaks tell how many lines follow
        for ($j = $end + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if ($token->code !== 'T_WHITESPACE') {
                if ($token->line === $line) {
                    continue;
                }

                $at = $token->pos;
                $text = '';
                break;
            }

            $text = $token->text;
            $at = $token->pos;
            if ($token->line === $line) {
                // The line break that ends the declaration's line is its.
                $break = strpos($text, "\n");
                if ($break === false || ($break + 1) === strlen($text)) {
                    $at = null;
                    continue;
                }

                $at += $break + 1;
                $text = substr($text, $break + 1);
            }

            if ($j === ($count - 1)) {
                return; // only whitespace to the end of the file
            }

            break;
        }

        if ($at === null) {
            if (($end + 1) >= $count || $tokens[$count - 1]->code === 'T_WHITESPACE') {
                return;
            }

            $at = $tokens[$count - 1]->pos; // `namespace A; echo 1;` and nothing after it
            $text = '';
        }

        $blank = substr_count($text, "\n");
        if ($blank === 1) {
            return;
        }

        $eol = str_contains($context->file->contents, "\r\n") ? "\r\n" : "\n";
        $edit = $blank === 0
            ? TextEdit::insert($at, $eol)
            : TextEdit::replace(new Span($at, $at + (int) strrpos($text, "\n") + 1), $eol);
        Spacing::report(
            $this->report,
            $context,
            $tokens[$namespace],
            'There must be one blank line after the namespace declaration',
            ['PSR2.Namespaces.NamespaceDeclaration.BlankLineAfter'],
            [$edit],
        );
    }

    /**
     * `findEndOfStatement()` of the `namespace` keyword: the closing brace of a braced
     * declaration, else its `;` (or the last token before a closing tag).
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function end(array $tokens, int $namespace): int
    {
        $count = count($tokens);
        $last = $namespace;
        for ($k = $namespace + 1; $k < $count; $k++) {
            $code = $tokens[$k]->code;
            if ($code === 'T_SEMICOLON') {
                return $k;
            }

            if ($code === 'T_OPEN_CURLY_BRACKET' && $tokens[$k]->closer !== null) {
                return $tokens[$k]->closer;
            }

            if ($code === 'T_CLOSE_TAG' || $code === 'T_OPEN_TAG') {
                return $last;
            }

            if (!Spacing::isEmpty($tokens[$k])) {
                $last = $k;
            }
        }

        return $count - 1;
    }
}
