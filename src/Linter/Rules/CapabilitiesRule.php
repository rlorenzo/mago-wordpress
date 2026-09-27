<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function in_array;

/**
 * Ports `WordPress.WP.Capabilities`.
 *
 * Only a capability passed as a single string literal is checked; the sniff's
 * low-severity `Undetermined` warning for any other value is hidden by PHPCS's
 * default severity and is not ported. The role check overlaps Mago's core
 * `no-roles-as-capabilities` rule.
 */
final class CapabilitiesRule extends CallRule
{
    /**
     * Function name => [0-indexed position, parameter name] of the capability argument.
     *
     * @var array<string, array{int, string}>
     */
    private const TARGETS = [
        'add_comments_page' => [2, 'capability'],
        'add_dashboard_page' => [2, 'capability'],
        'add_links_page' => [2, 'capability'],
        'add_management_page' => [2, 'capability'],
        'add_media_page' => [2, 'capability'],
        'add_menu_page' => [2, 'capability'],
        'add_object_page' => [2, 'capability'],
        'add_options_page' => [2, 'capability'],
        'add_pages_page' => [2, 'capability'],
        'add_plugins_page' => [2, 'capability'],
        'add_posts_page' => [2, 'capability'],
        'add_submenu_page' => [3, 'capability'],
        'add_theme_page' => [2, 'capability'],
        'add_users_page' => [2, 'capability'],
        'add_utility_page' => [2, 'capability'],
        'author_can' => [1, 'capability'],
        'current_user_can' => [0, 'capability'],
        'current_user_can_for_blog' => [1, 'capability'],
        'map_meta_cap' => [0, 'cap'],
        'user_can' => [1, 'capability'],
    ];

    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/capabilities',
            name: 'WordPress capabilities',
            description: 'Reports roles, deprecated capabilities, and unknown capabilities passed to current_user_can(), add_menu_page() and the other capability-checking functions. Custom capabilities can be listed in the `custom-capabilities` setting.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(self::TARGETS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        if (Calls::isUnpacked($call)) {
            return;
        }

        [$index, $parameter] = self::TARGETS[$name];
        $value = $this->argument($context, $call, $index, $parameter);
        $capability = $value === null ? null : Values::literalString($context->file, $value);
        if ($value === null || $capability === null || in_array($capability, Lists::CORE_CAPABILITIES, strict: true)) {
            return;
        }

        $deprecatedSince = Lists::DEPRECATED_CAPABILITIES[$capability] ?? null;
        $issue = match (true) {
            $capability === '' => Issue::new(
                "An empty string is not a valid capability in `{$name}()`.",
                $value->span,
            )->withHelp('Pass the capability the user needs.'),
            in_array($capability, $this->settings->customList('custom-capabilities'), strict: true) => null,
            $deprecatedSince !== null => Issue::new(
                "The capability `{$capability}` in `{$name}()` has been deprecated since WordPress {$deprecatedSince}.",
                $value->span,
            )->withHelp('Use a named capability such as `manage_options` or `edit_posts` instead of a user level.'),
            in_array($capability, Lists::CORE_ROLES, strict: true) => Issue::new(
                "The role `{$capability}` is used as a capability in `{$name}()`.",
                $value->span,
            )->withHelp('Check for a capability the role grants instead of the role itself.'),
            default => Issue::new("Unknown capability `{$capability}` in `{$name}()`.", $value->span)->withNote(
                'A custom capability must be registered with WP_Role::add_cap().',
            )->withHelp('Check the spelling, or add the capability to the `custom-capabilities` setting.'),
        };

        if ($issue !== null) {
            $context->report($issue);
        }
    }
}
