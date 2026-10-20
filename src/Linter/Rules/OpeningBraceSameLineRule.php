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

use function preg_match;
use function strtolower;

/**
 * Ports `Generic.Classes.OpeningBraceSameLine`: the `{` of a class, interface, trait or enum on the
 * line of the declaration (`BraceOnNewLine`), nothing after it on its line unless the body is
 * empty (`ContentAfterBrace`) and one space before it (`SpaceBeforeBrace`). Fixes all three, as
 * phpcbf does. Not ported: `MissingBrace`, a parse error Mago reports itself.
 */
final class OpeningBraceSameLineRule implements Rule
{
    private const SNIFF = 'Generic.Classes.OpeningBraceSameLine';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/opening-brace-same-line',
            name: 'Opening brace on the same line',
            description: 'Reports a class, interface, trait or enum whose opening brace is on a line of its own, has code after it, or is not one space after the declaration.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (preg_match('/\b(?:class|interface|trait|enum)\b/i', $context->file->contents) !== 1) {
            return;
        }

        $layout = Layout::of($context->file);
        $issues = new LayoutIssues($this->report, $context, $layout, self::SNIFF);
        foreach ($layout->classLikes() as $keyword) {
            $brace = $layout->t[$keyword]->scopeOpener;
            if ($brace !== null) {
                $this->check($layout, $issues, $keyword, $brace);
            }
        }
    }

    private function check(Layout $layout, LayoutIssues $issues, int $keyword, int $brace): void
    {
        $nameAt = $layout->next('T_STRING', $keyword + 1);
        $name = strtolower($layout->t[$keyword]->text) . ' ' . ($nameAt === null ? '' : $layout->t[$nameAt]->text);
        $lastClassLineToken = $layout->previous('T_WHITESPACE', $brace - 1, $keyword, exclude: true) ?? $keyword;
        $lineDifference = $layout->line($brace) - $layout->endLine[$lastClassLineToken];
        if ($lineDifference > 0) {
            $fix = $issues->fix();
            $fix->after($lastClassLineToken, ' {');
            $fix->replace($brace, '');
            $issues->error(
                $brace,
                'Opening brace should be on the same line as the declaration for ' . $name,
                'BraceOnNewLine',
                $fix,
            );
        }

        $next = $layout->next('T_WHITESPACE', $brace + 1, null, exclude: true);
        if ($next !== null && $layout->line($next) === $layout->line($brace)) {
            if ($next === $layout->t[$keyword]->scopeCloser) {
                return;
            }

            $fix = $issues->fix();
            $fix->newlineAfter($brace);
            $issues->error($brace, 'Opening brace must be the last content on the line', 'ContentAfterBrace', $fix);
        }

        if ($lineDifference > 0) {
            return;
        }

        $before = $brace - 1;
        $length = $layout->code($before) === 'T_WHITESPACE' ? $layout->length[$before] : 0;
        if ($length === 1) {
            return;
        }

        $fix = $issues->fix();
        if ($length === 0) {
            $fix->before($brace, ' ');
        } else {
            $fix->replace($before, ' ');
        }

        $issues->error($brace, 'Expected 1 space before opening brace; found ' . $length, 'SpaceBeforeBrace', $fix);
    }
}
