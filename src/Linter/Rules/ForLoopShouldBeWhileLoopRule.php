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
use Rlorenzo\MagoWordPress\Internal\LoopHeader;
use Rlorenzo\MagoWordPress\Internal\Report;

use function strlen;

/**
 * Ports `Generic.CodeAnalysis.ForLoopShouldBeWhileLoop`: a `for` with a test and neither an
 * initializer nor an update, `for ( ; $it->valid(); )`. Like the sniff it does not report `for ( ; ; )`,
 * which has no test, and it does not look inside the parts: a comment alone does not make one.
 */
final class ForLoopShouldBeWhileLoopRule implements Rule
{
    private const CODE = 'Generic.CodeAnalysis.ForLoopShouldBeWhileLoop.CanSimplify';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/for-loop-should-be-while-loop',
            name: 'For loop should be while loop',
            description: 'Reports a `for` loop with only a test part, which a `while` loop expresses better.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::For],
        );
    }

    public function lint(LintContext $context): void
    {
        $parts = [0, 0, 0];
        $index = 0;
        foreach (LoopHeader::tokens($context->file, $context->node) as $token) {
            if ($token->text === ';') {
                $index++;
            } elseif (!$token->isIgnorable() && $index < 3) {
                $parts[$index]++;
            }
        }

        if ($parts[0] !== 0 || $parts[2] !== 0 || $parts[1] === 0) {
            return;
        }

        $start = $context->node->span->start;
        $this->report->issue(
            $context,
            Issue::new(
                'This FOR loop can be simplified to a WHILE loop',
                new Span($start, $start + strlen('for')),
                'only a test part',
            )->withHelp('Write `while ( test )`.'),
            [self::CODE],
        );
    }
}
