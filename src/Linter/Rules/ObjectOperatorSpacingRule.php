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

use function strlen;
use function strpos;
use function strtolower;

/**
 * Ports `WordPress.WhiteSpace.ObjectOperatorSpacing` (`Before`, `After`) with `ignoreNewlines`
 * on, as WordPress-Core sets it: no whitespace around `->`, `?->` and `::`, except a line break
 * (which `::class` does not get).
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ObjectOperatorSpacingRule implements Rule
{
    private const SNIFF = 'WordPress.WhiteSpace.ObjectOperatorSpacing';

    private const OPERATORS = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_DOUBLE_COLON' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/object-operator-spacing',
            name: 'Object operator spacing',
            description: 'Forbids whitespace around `->`, `?->` and `::`, except line breaks (but `::class` allows none).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $source = $context->file->contents;
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if (!isset(self::OPERATORS[$token->code])) {
                continue;
            }

            $ignoreNewlines = true;
            if ($token->code === 'T_DOUBLE_COLON') {
                $next = PhpcsTokenStream::next($tokens, $i);
                $ignoreNewlines =
                    $next === null
                    || $tokens[$next]->code !== 'T_STRING'
                    || strtolower($tokens[$next]->text) !== 'class';
            }

            $before = Spacing::gap($source, $tokens, $i, after: false);
            if ($before !== 0 && ($before !== 'newline' || !$ignoreNewlines)) {
                $ws = $tokens[$i - 1];
                $start = $ws->pos;
                // A line comment owns its line break; only the indentation after it goes.
                if (
                    isset($tokens[$i - 2])
                    && Spacing::isLineComment($tokens[$i - 2])
                    && ($ws->text[0] === "\n" || $ws->text[0] === "\r")
                ) {
                    $start += strpos($ws->text, "\n") + 1;
                }

                $this->flag($context, $token, 'Space found before object operator', 'Before', $start, $ws);
            }

            if (!isset($tokens[$i + 2])) {
                continue;
            }

            $after = Spacing::gap($source, $tokens, $i, after: true);
            if ($after !== 0 && ($after !== 'newline' || !$ignoreNewlines)) {
                $ws = $tokens[$i + 1];
                $this->flag($context, $token, 'Space found after object operator', 'After', $ws->pos, $ws);
            }
        }
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function flag(
        LintContext $context,
        PhpcsToken $token,
        string $message,
        string $code,
        int $start,
        PhpcsToken $ws,
    ): void {
        Spacing::report(
            $this->report,
            $context,
            $token,
            $message,
            [self::SNIFF . '.' . $code],
            [TextEdit::delete(new Span($start, $ws->pos + strlen($ws->text)))],
        );
    }
}
