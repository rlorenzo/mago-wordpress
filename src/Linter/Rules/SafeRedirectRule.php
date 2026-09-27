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
 * Ports `WordPress.Security.SafeRedirect`.
 */
final class SafeRedirectRule extends CallRule
{
    private const SNIFF = 'WordPress.Security.SafeRedirect';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/safe-redirect',
            name: 'Safe redirect',
            description: 'Reports wp_redirect() calls. It does not validate the target host, so a user-influenced URL becomes an open redirect.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['wp_redirect'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        Report::issue(
            $context,
            Issue::new('wp_redirect() does not validate the redirect target.', $context->node->span)->withHelp(
                'Use wp_safe_redirect(), and add hosts through the allowed_redirect_hosts filter when needed.',
            ),
            [self::SNIFF . '.wp_redirect_wp_redirect'],
        );
    }
}
