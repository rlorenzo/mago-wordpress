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
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function ltrim;
use function str_starts_with;
use function strspn;
use function strtolower;

/**
 * Ports `Universal.CodeAnalysis.NoEchoSprintf`: `echo sprintf(...);` and
 * `echo vsprintf(...);`, which `printf()` and `vprintf()` do directly. The fix is phpcbf's:
 * drop the `echo` and rename the call.
 */
final class NoEchoSprintfRule implements Rule
{
    private const CODE = 'Universal.CodeAnalysis.NoEchoSprintf.Found';

    private const TARGETS = ['sprintf' => 'printf', 'vsprintf' => 'vprintf'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/no-echo-sprintf',
            name: 'No echo sprintf',
            description: 'Reports `echo sprintf(...)` and `echo vsprintf(...)`; use `printf()` and `vprintf()`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Echo],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        // `echo`, one expression and the terminator: `echo sprintf(...), $x;` is left alone.
        if (count($children) !== 3 || $children[2]->kind !== NodeKind::Terminator) {
            return;
        }

        $outer = $file->getChildren($children[1])[0] ?? null;
        $call = $outer?->kind === NodeKind::Call ? $file->getChildren($outer)[0] ?? null : null;
        $name = $call?->kind === NodeKind::FunctionCall ? $file->getChildren($call)[0] ?? null : null;
        if ($name === null) {
            return;
        }

        $written = $file->getText($name);
        $replacement = self::TARGETS[strtolower(ltrim($written, characters: '\\'))] ?? null;
        if ($replacement === null) {
            return;
        }

        $keyword = $children[0];
        $gap = strspn($file->contents, characters: " \t\r\n", offset: $keyword->span->end);
        $this->report->issue(
            $context,
            Issue::new(
                "Unnecessary \"echo {$written}(...)\" found. Use \"{$replacement}(...)\" instead.",
                $name->span,
                'echoes its result',
            )
                ->withHelp("Call `{$replacement}()` directly.")
                ->withEdit(TextEdit::delete(new Span($keyword->span->start, $keyword->span->end + $gap)))
                ->withEdit(TextEdit::replace(
                    $name->span,
                    (str_starts_with($written, '\\') ? '\\' : '') . $replacement,
                )),
            [self::CODE],
        );
    }
}
