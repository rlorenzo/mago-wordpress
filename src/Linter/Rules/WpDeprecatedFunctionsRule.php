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
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function preg_match;
use function trim;
use function version_compare;

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
        if (!$this->isReportable($entry['version'])) {
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

    /**
     * Whether a function deprecated since $deprecatedSince should be
     * reported under the project's configured minimum WordPress version.
     *
     * An empty or unparsable `minimum-wp-version` reports every function in
     * the table, matching the ported sniff. `version_compare()` treats a
     * shorter version as older than the same version with a trailing `.0`
     * (`"4.5" < "4.5.0"`), so the minimum is padded to three components
     * before it is compared against the table's `major.minor.patch` entries.
     */
    private function isReportable(string $deprecatedSince): bool
    {
        $minimum = trim($this->settings->minimumWpVersion);
        $parts = [];
        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $minimum, $parts) !== 1) {
            return true;
        }

        $normalizedMinimum = ($parts[1] ?? '0') . '.' . ($parts[2] ?? '0') . '.' . ($parts[3] ?? '0');

        return version_compare($deprecatedSince, $normalizedMinimum, operator: '<=');
    }
}
