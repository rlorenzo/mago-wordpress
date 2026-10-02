<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function count;
use function preg_match;
use function preg_replace;
use function strtoupper;
use function trim;

/**
 * Ports `Modernize.FunctionCalls.Dirname` with the code WordPress-Extra keeps, `FileConstant`:
 * `dirname(__FILE__)` is `__DIR__`. The fix, as phpcbf's, replaces the call with `__DIR__`, or
 * with `$levels` above 1 replaces `__FILE__` and lowers `$levels` by one; a call holding a
 * comment, or with a `$levels` that is not an integer literal, is reported without a fix.
 * `Nested` (nested `dirname()` calls) is not ported: WordPress-Extra turns it off.
 */
final class DirnameRule extends CallRule
{
    private const CODE = 'Modernize.FunctionCalls.Dirname.FileConstant';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/dirname',
            name: 'Dirname',
            description: 'Reports `dirname(__FILE__)`, which is the `__DIR__` constant.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['dirname'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $file = $context->file;
        $count = count($call->arguments);
        if ($count === 0 || $count > 2 || Calls::isUnpacked($call)) {
            return;
        }

        $path = $this->argument($context, $call, 0, 'path');
        $levels = $this->argument($context, $call, 1, 'levels');
        if ($path === null || $levels === null && $count === 2) {
            return;
        }

        $clean = preg_replace('`/\*.*?\*/|(?://|#)[^\n]*`s', '', $file->getText($path));
        if (strtoupper(trim((string) $clean)) !== '__FILE__') {
            return;
        }

        $issue = Issue::new(
            'Use the __DIR__ constant instead of calling dirname(__FILE__) (PHP >= 5.3)',
            $context->node->span,
            'dirname(__FILE__)',
        )->withHelp('Use `__DIR__`.');

        $levelsText = $levels === null ? '1' : trim($file->getText($levels));
        if (!Calls::hasComment($file, $context->node->span) && preg_match('/^\d+$/', $levelsText) === 1) {
            $value = (int) $levelsText;
            $issue =
                $levels === null || $value === 1
                    ? $issue->withEdit(TextEdit::replace($context->node->span, '__DIR__'))
                    : $issue
                        ->withEdit(TextEdit::replace($path->span, '__DIR__'))
                        ->withEdit(TextEdit::replace($levels->span, (string) ($value - 1)));
        }

        $this->report->issue($context, $issue, [self::CODE]);
    }
}
