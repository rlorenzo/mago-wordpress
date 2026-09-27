<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

/**
 * Ports `WordPress.PHP.RestrictedPHPFunctions`.
 */
final class RestrictedPhpFunctionsRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/restricted-php-functions',
            name: 'Restricted PHP function',
            description: 'Reports calls to create_function(), which performs an eval() internally and was removed in PHP 8.0.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return Lists::RESTRICTED_PHP_FUNCTIONS;
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $context->report(Issue::new(
            "{$name}() internally performs an eval(), which makes this a very dangerous function.",
            $context->node->span,
        )->withHelp('Use an anonymous function, or declare a named function instead.'));
    }
}
