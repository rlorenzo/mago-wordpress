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

use function sprintf;
use function strlen;

/**
 * Ports `Generic.WhiteSpace.IncrementDecrementSpacing`: no whitespace between `++` or `--`
 * and the variable (or constant, or property) it applies to, before or after it.
 * @mago-expect lint:cyclomatic-complexity
 */
final class IncrementDecrementSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/increment-decrement-spacing',
            name: 'Increment/decrement spacing',
            description: 'Disallows whitespace between an increment or decrement operator and its operand.',
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
            if ($token->code !== 'T_INC' && $token->code !== 'T_DEC') {
                continue;
            }

            $name = $token->code === 'T_DEC' ? 'decrement' : 'increment';
            $next = PhpcsTokenStream::next($t, $i);
            if ($next !== null && ($t[$next]->code === 'T_VARIABLE' || $t[$next]->code === 'T_STRING')) {
                if ($next !== ($i + 1)) {
                    $this->report(
                        $context,
                        $source,
                        $t,
                        $i,
                        $next,
                        $name,
                        sprintf('Expected no spaces between the %s operator and %s; %%s found', $name, $t[$next]->text),
                        new Span($token->pos + strlen($token->text), $t[$next]->pos),
                    );
                }

                continue;
            }

            $prev = PhpcsTokenStream::previous($t, $i);
            if (
                $prev !== null
                && in_array($t[$prev]->code, ['T_VARIABLE', 'T_STRING', 'T_CLOSE_SQUARE_BRACKET'], strict: true)
                && $prev !== ($i - 1)
            ) {
                $this->report(
                    $context,
                    $source,
                    $t,
                    $i,
                    $prev,
                    $name,
                    sprintf('Expected no spaces between %s and the %s operator; %%s found', $t[$prev]->text, $name),
                    new Span($t[$prev]->pos + strlen($t[$prev]->text), $token->pos),
                );
            }
        }
    }

    /**
     * @param list<PhpcsToken> $t
     * @mago-expect lint:excessive-parameter-list
     */
    private function report(
        LintContext $context,
        string $source,
        array $t,
        int $i,
        int $operand,
        string $name,
        string $message,
        Span $gap,
    ): void {
        [$from, $to] = $operand > $i ? [$i, $operand] : [$operand, $i];
        $onlyWs = true;
        for ($k = $from + 1; $k < $to; $k++) {
            $onlyWs = $onlyWs && $t[$k]->code === 'T_WHITESPACE';
        }

        $spaces = 'comment';
        if ($onlyWs) {
            $spaces = Spacing::endLine($t[$from]) !== $t[$to]->line
                ? 'newline'
                : (string) Spacing::width($source, $t[$from + 1]);
        }

        Spacing::report(
            $this->report,
            $context,
            $t[$i],
            sprintf($message, $spaces),
            [
                'Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfter'
                    . ($name === 'increment' ? 'Increment' : 'Decrement'),
            ],
            $onlyWs ? [TextEdit::delete($gap)] : [],
        );
    }
}
