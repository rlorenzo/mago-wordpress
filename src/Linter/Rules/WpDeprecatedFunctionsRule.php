<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Internal\WordPress\WpVersion;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;

/**
 * Ports `WordPress.WP.DeprecatedFunctions`.
 *
 * Every deprecated function is reported, as in WPCS; a deprecation newer than
 * the `minimum-wp-version` setting carries a note saying so (WPCS lowers it to
 * a warning, which a Mago issue cannot do per report).
 */
final class WpDeprecatedFunctionsRule extends CallRule
{
    private const SNIFF = 'WordPress.WP.DeprecatedFunctions';

    public function __construct(
        private readonly Report $report,
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
            targets: [NodeKind::FunctionCall, NodeKind::FunctionPartialApplication, NodeKind::TypedUseItemSequence],
        );
    }

    protected function names(): array
    {
        return array_keys(Lists::DEPRECATED_FUNCTIONS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $this->inspectReference($context, $context->node, $name);
    }

    protected function inspectReference(LintContext $context, Node $reference, string $name): void
    {
        $entry = Lists::DEPRECATED_FUNCTIONS[$name];
        $pending = WpVersion::pendingNote($this->settings->normalizedMinimumWpVersion(), $entry['version']);

        $help = $entry['alt'] === ''
            ? 'There is no direct replacement; remove the call or implement the behavior manually.'
            : "Use `{$entry['alt']}` instead.";

        $issue = Issue::new(
            "`{$name}()` has been deprecated since WordPress {$entry['version']}.",
            $reference->span,
        )->withNote('Deprecated WordPress functions may be removed in a future release.')->withHelp($help);
        if ($pending !== null) {
            $issue = $issue->withNote($pending);
        }

        $this->report->issue($context, $issue, [self::SNIFF . ".{$name}Found"]);
    }
}
