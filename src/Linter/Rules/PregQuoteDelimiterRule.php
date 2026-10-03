<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

/**
 * Ports `WordPress.PHP.PregQuoteDelimiter`: a `preg_quote()` call with arguments but no
 * `$delimiter`.
 */
final class PregQuoteDelimiterRule extends CallRule
{
    private const SNIFF = 'WordPress.PHP.PregQuoteDelimiter';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/preg-quote-delimiter',
            name: 'preg_quote() delimiter',
            description: 'Reports preg_quote() calls that do not pass the $delimiter argument, so the regex delimiter is left unescaped.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['preg_quote'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $second = $call->arguments[1] ?? null;
        if (
            $call->arguments === []
            || $second !== null && $second->name === null
            || $this->argument($context, $call, -1, parameter: 'delimiter') !== null
        ) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Passing the $delimiter parameter to preg_quote() is strongly recommended.',
                $context->node->span,
            )->withHelp('Pass the regex delimiter, e.g. `preg_quote($value, \'/\')`, so it is escaped too.'),
            [self::SNIFF . '.Missing'],
        );
    }
}
