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
use Rlorenzo\MagoWordPress\Internal\LoopHeader;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_values;
use function in_array;
use function strlen;

/**
 * Ports `Generic.CodeAnalysis.ForLoopWithTestFunctionCall`: a function or method call in
 * the test part of a `for` loop runs on every iteration.
 */
final class ForLoopWithTestFunctionCallRule implements Rule
{
    private const CODE = 'Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed';

    /** phpcs's T_STRING / T_VARIABLE; PHP 8 name tokens are T_STRING runs to phpcs. */
    private const CALLEES = [T_STRING, T_VARIABLE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/for-loop-with-test-function-call',
            name: 'For loop with test function call',
            description: 'Reports a function or method call in the test part of a `for` loop, which runs again on every iteration; compute the value once before the loop.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::For],
        );
    }

    public function lint(LintContext $context): void
    {
        // Whitespace and comments never matter here: the sniff skips them before the `(`.
        $tokens = array_values(array_filter(
            LoopHeader::tokens($context->file, $context->node),
            static fn(PhpToken $token): bool => !$token->isIgnorable(),
        ));
        $part = 0;
        foreach ($tokens as $i => $token) {
            $part += $token->text === ';' ? 1 : 0;
            if ($part > 1) {
                return;
            }

            $callee = $part === 1 && in_array($token->id, self::CALLEES, strict: true);
            if ($callee && ($tokens[$i + 1] ?? null)?->text === '(') {
                $start = $context->node->span->start;
                $this->report->issue(
                    $context,
                    Issue::new(
                        'Avoid function calls in a FOR loop test part',
                        new Span($start, $start + strlen('for')),
                        'the test part calls a function on every iteration',
                    )->withHelp('Assign the result to a variable before the loop and test the variable.'),
                    [self::CODE],
                );

                return;
            }
        }
    }
}
