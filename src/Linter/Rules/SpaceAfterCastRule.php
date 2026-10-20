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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function sprintf;
use function strlen;

/**
 * Ports `Generic.Formatting.SpaceAfterCast` with its defaults (`spacing` 1, `ignoreNewlines`
 * false), which WordPress-Core keeps: exactly one space after a type cast.
 */
final class SpaceAfterCastRule implements Rule
{
    private const SNIFF = 'Generic.Formatting.SpaceAfterCast';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/space-after-cast',
            name: 'Space after cast',
            description: 'Requires exactly one space after a type cast.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        $source = $context->file->contents;
        foreach ($tokens as $i => $token) {
            if (!isset(Spacing::CASTS[$token->code])) {
                continue;
            }

            $nextNonEmpty = PhpcsTokenStream::next($tokens, $i);
            if ($nextNonEmpty === null) {
                continue;
            }

            $nextNonWs = $i + 1;
            while (Spacing::isWs($tokens, $nextNonWs)) {
                $nextNonWs++;
            }

            if ($nextNonWs !== $nextNonEmpty) {
                Spacing::report(
                    $this->report,
                    $context,
                    $token,
                    'Expected 1 space after cast statement; comment found',
                    [self::SNIFF . '.CommentFound'],
                );
                continue;
            }

            $found = 0;
            if ($tokens[$nextNonEmpty]->line !== $token->line) {
                $found = 'newline';
            } elseif (Spacing::isWs($tokens, $i + 1)) {
                $found = Spacing::width($source, $tokens[$i + 1]);
            }

            if ($found === 1) {
                continue;
            }

            $end = $token->pos + strlen($token->text);
            Spacing::report(
                $this->report,
                $context,
                $token,
                sprintf('Expected 1 space after cast statement; %s found', $found),
                [self::SNIFF . '.' . ($found === 0 ? 'NoSpace' : 'TooMuchSpace')],
                [
                    $found === 0
                        ? TextEdit::insert($end, ' ')
                        : TextEdit::replace(new Span($end, $tokens[$nextNonEmpty]->pos), ' '),
                ],
            );
        }
    }
}
