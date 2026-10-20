<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function strlen;

/**
 * Ports `Squiz.Strings.ConcatenationSpacing` as WordPress-Core sets it (`spacing` 1,
 * `ignoreNewlines` true): one space on each side of the `.` operator, a line break being fine
 * and the space before it left alone after a heredoc's closing marker.
 */
final class ConcatenationSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/concatenation-spacing',
            name: 'Concatenation spacing',
            description: 'Requires a single space on each side of the `.` concatenation operator, a line break being fine.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $t = Spacing::tokens($context);
        $source = $context->file->contents;
        foreach ($t as $i => $token) {
            if ($token->code !== 'T_STRING_CONCAT' || !isset($t[$i + 2])) {
                continue;
            }

            $prev = PhpcsTokenStream::previous($t, $i);
            $ignoreBefore =
                $prev !== null && ($t[$prev]->code === 'T_END_HEREDOC' || $t[$prev]->code === 'T_END_NOWDOC');
            $before = Spacing::gap($source, $t, $i, after: false);
            $after = Spacing::gap($source, $t, $i, after: true);
            if (($ignoreBefore || $before === 1 || $before === 'newline') && ($after === 1 || $after === 'newline')) {
                continue;
            }

            $edits = [];
            if (!$ignoreBefore && $before !== 'newline') {
                $ws = Spacing::wsBefore($t, $i);
                $edits[] = $ws !== null ? Spacing::replace($ws, ' ') : TextEdit::insert($token->pos, ' ');
            }

            if ($after !== 'newline') {
                $edits[] = Spacing::isWs($t, $i + 1)
                    ? Spacing::replace($t[$i + 1], ' ')
                    : TextEdit::insert($token->pos + strlen($token->text), ' ');
            }

            Spacing::report(
                $this->report,
                $context,
                $token,
                'Concat operator must be surrounded by a single space',
                ['Squiz.Strings.ConcatenationSpacing.PaddingFound'],
                $edits,
            );
        }
    }
}
