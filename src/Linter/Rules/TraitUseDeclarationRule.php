<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Layout;
use Rlorenzo\MagoWordPress\Internal\LayoutIssues;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_keys;
use function count;
use function sprintf;
use function stripos;
use function strtolower;

/**
 * Ports `PSR12.Traits.UseDeclaration` as WordPress-Core sets it: the `use` statements that import
 * traits into a class, interface-less body, each on a line of its own and grouped, one trait per
 * statement, a blank line after the last one unless it ends the class, and the layout of the
 * `{ ... }` group of `insteadof` and `as` rules. Fixes what phpcbf fixes. WordPress-Core turns
 * off `UseAfterBrace`, `SpaceAfterUse`, `SpaceBeforeComma` and `SpaceAfterComma`
 * (`Generic.WhiteSpace.LanguageConstructSpacing` and `Universal.WhiteSpace.CommaSpacing` cover
 * the last three), so those are not ported.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:halstead
 * @mago-expect lint:excessive-parameter-list
 */
final class TraitUseDeclarationRule implements Rule
{
    private const SNIFF = 'PSR12.Traits.UseDeclaration';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/trait-use-declaration',
            name: 'Trait use declaration',
            description: 'Reports trait `use` statements in a class that are not one per line, grouped, followed by a blank line, or whose `insteadof`/`as` block is not laid out as PSR-12 asks.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        if (stripos($contents, 'use') === false) {
            return;
        }

        $layout = Layout::of($context->file);
        $issues = new LayoutIssues($this->report, $context, $layout, self::SNIFF);
        foreach ($layout->ooScopes() as $oo) {
            $opener = $layout->t[$oo]->scopeOpener;
            $closer = $layout->t[$oo]->scopeCloser;
            if ($opener === null || $closer === null) {
                continue;
            }

            $uses = $this->uses($layout, $opener, $closer);
            if ($uses !== []) {
                $this->process($layout, $issues, $oo, $closer, $uses);
            }
        }
    }

    /**
     * The `use` statements directly in the body, each with the `{` of its conflict resolution
     * block (null for a plain statement).
     *
     * @return array<int, null|int>
     */
    private function uses(Layout $layout, int $opener, int $closer): array
    {
        $uses = [];
        for ($i = $opener + 1; $i < $closer; $i++) {
            $token = $layout->t[$i];
            if ($token->code === 'T_USE') {
                $group = null;
                for ($j = $i + 1; $j < $closer; $j++) {
                    $code = $layout->t[$j]->code;
                    if ($code === 'T_SEMICOLON') {
                        break;
                    }

                    if ($code === 'T_OPEN_CURLY_BRACKET' && $layout->t[$j]->closer !== null) {
                        $group = $j;
                        break;
                    }
                }

                $uses[$i] = $group;
                if ($group !== null) {
                    $i = $layout->t[$group]->closer ?? $i;
                }

                continue;
            }

            if ($token->scopeCloser !== null) {
                $i = $token->scopeCloser;
            } elseif ($token->code === 'T_OPEN_CURLY_BRACKET' && $token->closer !== null) {
                $i = $token->closer;
            }
        }

        return $uses;
    }

    /**
     * @param array<int, null|int> $uses
     */
    private function process(Layout $layout, LayoutIssues $issues, int $oo, int $closer, array $uses): void
    {
        $tokens = array_keys($uses);
        $first = $tokens[0];
        $count = count($tokens);
        foreach ($tokens as $position => $use) {
            if ($position > 0) {
                $this->sameLine($layout, $issues, $use, $first);
            }

            $group = $uses[$use];
            if ($group !== null) {
                $this->group($layout, $issues, $use, $group, $layout->t[$group]->closer ?? $group);
                $end = $layout->t[$group]->closer ?? $group;
            } else {
                $end = $this->statement($layout, $issues, $use);
                if ($end === null) {
                    return;
                }
            }

            if ($position === ($count - 1)) {
                $this->afterLast($layout, $issues, $oo, $closer, $use, $end);
            } else {
                $next = $layout->nextContent($end + 1);
                if ($next !== $tokens[$position + 1]) {
                    $issues->error($tokens[$position + 1], 'Imported traits must be grouped together', 'NotGrouped');
                }
            }
        }
    }

    private function sameLine(Layout $layout, LayoutIssues $issues, int $use, int $first): void
    {
        $prev = $layout->previousContent($use - 1);
        if ($prev === null || $layout->line($prev) !== $layout->line($use)) {
            return;
        }

        $message = 'Each imported trait must be on its own line';
        $prevNonWhitespace = $layout->previous('T_WHITESPACE', $use - 1, null, exclude: true);
        if ($prevNonWhitespace !== $prev) {
            $issues->error($use, $message, 'SpacingBeforeImport');

            return;
        }

        $fix = $issues->fix();
        for ($x = $use - 1; $x > $prev; $x--) {
            if ($layout->line($x) !== $layout->line($use)) {
                $fix->replace($x, '');
            }
        }

        $fix->newlineAfter($prev);
        if ($layout->code($use - 1) === 'T_WHITESPACE') {
            $fix->replace($use - 1, '');
        }

        $fix->after($prev, Layout::spaces($layout->column[$first] - 1));
        $issues->error($use, $message, 'SpacingBeforeImport', $fix);
    }

    /** @return null|int the `;` that ends the statement */
    private function statement(Layout $layout, LayoutIssues $issues, int $use): ?int
    {
        $next = $layout->next(['T_COMMA', 'T_SEMICOLON'], $use + 1);
        if ($next !== null && $layout->code($next) === 'T_COMMA') {
            $fix = $issues->fix();
            $fix->replace($next, ';' . $layout->eol . Layout::spaces($layout->column[$use] - 1) . 'use ');
            $issues->error(
                $next,
                'Each imported trait must have its own "use" import statement',
                'MultipleImport',
                $fix,
            );
        }

        return $layout->next('T_SEMICOLON', $use + 1);
    }

    private function afterLast(Layout $layout, LayoutIssues $issues, int $oo, int $closer, int $use, int $end): void
    {
        $next = $layout->next('T_WHITESPACE', $end + 1, null, exclude: true);
        if ($next === $closer) {
            if ($layout->line($closer) > ($layout->line($end) + 1)) {
                $fix = $issues->fix();
                for ($i = $end + 1; $i < $closer; $i++) {
                    if ($layout->line($i) === $layout->line($end)) {
                        continue;
                    }

                    if ($layout->line($i) === $layout->line($closer)) {
                        break;
                    }

                    $fix->replace($i, '');
                }

                $issues->error(
                    $end,
                    sprintf(
                        'There must be no blank line after the last trait import statement at the bottom of a %s',
                        strtolower($layout->t[$oo]->text),
                    ),
                    'BlankLineAfterLastUse',
                    $fix,
                );
            }

            return;
        }

        if ($next === null || $layout->code($next) === 'T_USE') {
            return;
        }

        for ($next = $end + 1; $next < $closer; $next++) {
            if ($layout->code($next) === 'T_WHITESPACE') {
                continue;
            }

            if ($layout->isComment($next) && $layout->line($next) === $layout->line($end)) {
                continue;
            }

            break;
        }

        if ($layout->line($next) > ($layout->line($end) + 1)) {
            return;
        }

        $fix = $issues->fix();
        if ($layout->line($next) === $layout->line($use)) {
            $fix->before($next, $layout->eol . $layout->eol);
        } else {
            for ($i = $next - 1; $i > $end; $i--) {
                if ($layout->line($i) !== $layout->line($next)) {
                    break;
                }
            }

            $fix->newlineBefore($i + 1);
        }

        $issues->error(
            $end,
            'There must be a blank line following the last trait import statement',
            'NoBlankLineAfterUse',
            $fix,
        );
    }

    private function group(Layout $layout, LayoutIssues $issues, int $use, int $opener, int $closer): void
    {
        $this->openBrace($layout, $issues, $use, $opener);
        $next = $layout->nextContent($opener + 1, $closer - 1);
        if ($next !== null && $layout->line($next) !== ($layout->line($opener) + 1)) {
            $message = 'First trait conflict resolution statement must be on the line after the opening brace';
            $nextNonWhitespace = $layout->next('T_WHITESPACE', $opener + 1, $closer - 1, exclude: true);
            $fix = null;
            if ($nextNonWhitespace === $next) {
                $fix = $issues->fix();
                for ($x = $opener + 1; $x < $next; $x++) {
                    if ($layout->line($x) === $layout->line($next)) {
                        break;
                    }

                    $fix->replace($x, '');
                }

                $fix->newlineAfter($opener);
            }

            $issues->error($opener, $message, 'SpaceAfterOpeningBrace', $fix);
        }

        for ($i = $opener + 1; $i < $closer; $i++) {
            match ($layout->code($i)) {
                'T_INSTEADOF' => $this->keyword($layout, $issues, $i, $opener, $closer, 'INSTEADOF', 'Insteadof'),
                'T_AS' => $this->keyword($layout, $issues, $i, $opener, $closer, 'AS', 'As'),
                'T_SEMICOLON' => $this->semicolon($layout, $issues, $i, $closer),
                default => null,
            };
        }

        $prev = $layout->previousContent($closer - 1, $opener + 1);
        if ($prev === null || $layout->line($prev) === ($layout->line($closer) - 1)) {
            return;
        }

        $message = 'Closing brace must be on the line after the last trait conflict resolution statement';
        $prevNonWhitespace = $layout->previous('T_WHITESPACE', $closer - 1, $opener + 1, exclude: true);
        $fix = null;
        if ($prevNonWhitespace === $prev) {
            $fix = $issues->fix();
            for ($x = $closer - 1; $x > $prev; $x--) {
                if ($layout->line($x) !== $layout->line($closer)) {
                    $fix->replace($x, '');
                }
            }

            $fix->newlineAfter($prev);
        }

        $issues->error($closer, $message, 'SpaceBeforeClosingBrace', $fix);
    }

    private function openBrace(Layout $layout, LayoutIssues $issues, int $use, int $opener): void
    {
        if ($layout->line($opener) !== $layout->line($use)) {
            $message = 'The opening brace of a trait import statement must be on the same line as the USE keyword';
            $canFix = true;
            for ($i = $use + 1; $i < $opener; $i++) {
                if ($layout->line($i) !== $layout->line($i + 1) && $layout->code($i) !== 'T_WHITESPACE') {
                    $canFix = false;
                    break;
                }
            }

            $fix = null;
            if ($canFix) {
                $fix = $issues->fix();
                for ($i = $use + 1; $i < $opener; $i++) {
                    if ($layout->line($i) !== $layout->line($i + 1)) {
                        $fix->replace($i, ' ');
                    }
                }
            }

            $issues->error($opener, $message, 'OpenBraceNewLine', $fix);
        }

        $message = 'Expected 1 space before opening brace in trait import statement; %s found';
        if ($layout->code($opener - 1) !== 'T_WHITESPACE') {
            $fix = $issues->fix();
            $fix->before($opener, ' ');
            $issues->error($opener, sprintf($message, '0'), 'SpaceBeforeOpeningBrace', $fix);
        } elseif ($layout->content($opener - 1) !== ' ') {
            $prev = $layout->previous('T_WHITESPACE', $opener - 1, null, exclude: true) ?? 0;
            $newline = $layout->line($prev) !== $layout->line($opener);
            $fix = $issues->fix();
            if ($newline) {
                for ($x = $opener - 1; $x > $prev; $x--) {
                    $fix->replace($x, '');
                }

                $fix->before($opener, ' ');
            } else {
                $fix->replace($opener - 1, ' ');
            }

            $issues->error(
                $opener,
                sprintf($message, $newline ? 'newline' : (string) $layout->length[$opener - 1]),
                'SpaceBeforeOpeningBrace',
                $fix,
            );
        }
    }

    private function keyword(
        Layout $layout,
        LayoutIssues $issues,
        int $i,
        int $opener,
        int $closer,
        string $word,
        string $codeWord,
    ): void {
        $message = 'Expected 1 space before ' . $word . ' in trait import statement; %s found';
        if ($layout->code($i - 1) !== 'T_WHITESPACE') {
            $fix = $issues->fix();
            $fix->before($i, ' ');
            $issues->error($i, sprintf($message, '0'), 'SpaceBefore' . $codeWord, $fix);
        } elseif ($layout->content($i - 1) !== ' ') {
            $prev = $layout->previous('T_WHITESPACE', $i - 1, $opener, exclude: true) ?? $opener;
            $newline = $layout->line($prev) !== $layout->line($i);
            $found = $newline ? 'newline' : (string) $layout->length[$i - 1];
            $fix = null;
            if ($layout->previousContent($i - 1, $opener) === $prev) {
                $fix = $issues->fix();
                if ($newline) {
                    for ($x = $i - 1; $x > $prev; $x--) {
                        $fix->replace($x, '');
                    }

                    $fix->before($i, ' ');
                } else {
                    $fix->replace($i - 1, ' ');
                }
            }

            $issues->error($i, sprintf($message, $found), 'SpaceBefore' . $codeWord, $fix);
        }

        $message = 'Expected 1 space after ' . $word . ' in trait import statement; %s found';
        if ($layout->code($i + 1) !== 'T_WHITESPACE') {
            $fix = $issues->fix();
            $fix->after($i, ' ');
            $issues->error($i, sprintf($message, '0'), 'SpaceAfter' . $codeWord, $fix);
        } elseif ($layout->content($i + 1) !== ' ') {
            $next = $layout->next('T_WHITESPACE', $i + 1, $closer, exclude: true) ?? $closer;
            $newline = $layout->line($next) !== $layout->line($i);
            $found = $newline ? 'newline' : (string) $layout->length[$i + 1];
            $fix = null;
            if ($layout->nextContent($i + 1, $closer) === $next) {
                $fix = $issues->fix();
                if ($newline) {
                    for ($x = $i + 1; $x < $next; $x++) {
                        $fix->replace($x, '');
                    }

                    $fix->after($i, ' ');
                } else {
                    $fix->replace($i + 1, ' ');
                }
            }

            $issues->error($i, sprintf($message, $found), 'SpaceAfter' . $codeWord, $fix);
        }
    }

    private function semicolon(Layout $layout, LayoutIssues $issues, int $i, int $closer): void
    {
        if ($layout->code($i - 1) === 'T_WHITESPACE') {
            $fix = $issues->fix();
            $fix->replace($i - 1, '');
            $issues->error(
                $i,
                sprintf(
                    'Expected no space before semicolon in trait import statement; %d found',
                    $layout->length[$i - 1],
                ),
                'SpaceBeforeSemicolon',
                $fix,
            );
        }

        $next = $layout->nextContent($i + 1, $closer - 1);
        if ($next === null || $layout->line($next) !== $layout->line($i)) {
            return;
        }

        $fix = null;
        if ($layout->next('T_WHITESPACE', $i + 1, $closer - 1, exclude: true) === $next) {
            $fix = $issues->fix();
            if ($layout->code($i + 1) === 'T_WHITESPACE') {
                $fix->replace($i + 1, '');
            }

            $fix->newlineAfter($i);
        }

        $issues->error(
            $i,
            'Each trait conflict resolution statement must be on a line by itself',
            'ConflictSameLine',
            $fix,
        );
    }
}
