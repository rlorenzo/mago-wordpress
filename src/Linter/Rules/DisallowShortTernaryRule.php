<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function substr;

/**
 * Ports `Universal.Operators.DisallowShortTernary`: `$a ?: $b`, and `$a ? /* comment *\/ : $b`.
 * Reported at the `?`, as the sniff does, which for a ternary broken over lines is not the line
 * the expression starts on.
 */
final class DisallowShortTernaryRule implements Rule
{
    private const CODE = 'Universal.Operators.DisallowShortTernary.Found';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-short-ternary',
            name: 'Disallow short ternary',
            description: 'Reports the short ternary `?:`; write the middle operand out.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Conditional],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        // The syntax tree leaves out the `?` and the `:`; a short ternary has no middle operand.
        if (($children[2] ?? null) !== null || ($children[1] ?? null) === null) {
            return;
        }

        $from = $children[0]->span->end;
        $gap = substr($file->contents, $from, $children[1]->span->start - $from);
        foreach (PhpToken::tokenize('<?php ' . $gap) as $token) {
            if ($token->text !== '?') {
                continue;
            }

            $at = $from + $token->pos - 6;
            $this->report->issue(
                $context,
                Issue::new(
                    'Using short ternaries is not allowed as they are rarely used correctly',
                    new Span($at, $at + 1),
                    'short ternary',
                )->withHelp('Write the middle operand: `$a ? $a : $b`.'),
                [self::CODE],
            );

            return;
        }
    }
}
