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
use Rlorenzo\MagoWordPress\Internal\WordPress\WpVersion;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;

/**
 * Ports `WordPress.WP.DeprecatedFunctions`.
 *
 * The `minimum-wp-version` setting restricts reports to functions that were
 * already deprecated in the project's oldest supported WordPress version.
 */
final class WpDeprecatedFunctionsRule extends CallRule
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/wp-deprecated-functions',
            name: 'WordPress deprecated functions',
            description: 'Reports calls to WordPress core functions that have been deprecated. A deprecated function may be removed in a future release and often has a modern replacement.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(Lists::DEPRECATED_FUNCTIONS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $entry = Lists::DEPRECATED_FUNCTIONS[$name];
        if (!WpVersion::reached($this->settings->normalizedMinimumWpVersion(), $entry['version'])) {
            return;
        }

        $help = $entry['alt'] === ''
            ? 'There is no direct replacement; remove the call or implement the behavior manually.'
            : "Use `{$entry['alt']}` instead.";

        $context->report(Issue::new(
            "`{$name}()` has been deprecated since WordPress {$entry['version']}.",
            $context->node->span,
        )->withNote('Deprecated WordPress functions may be removed in a future release.')->withHelp($help));
    }
}
