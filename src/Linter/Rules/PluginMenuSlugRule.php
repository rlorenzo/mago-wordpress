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
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_keys;
use function strtolower;

/**
 * Ports `WordPress.Security.PluginMenuSlug`.
 *
 * `__FILE__` used as a menu/parent slug leaks the plugin's filesystem path
 * to anyone who can see the resulting admin URL.
 */
final class PluginMenuSlugRule extends CallRule
{
    private const SNIFF = 'WordPress.Security.PluginMenuSlug';

    /**
     * Function name => [0-indexed parameter position => parameter name] for
     * every slot the sniff checks.
     *
     * @var array<string, array<int, string>>
     */
    private const SLOTS = [
        'add_comments_page' => [3 => 'menu_slug'],
        'add_dashboard_page' => [3 => 'menu_slug'],
        'add_links_page' => [3 => 'menu_slug'],
        'add_management_page' => [3 => 'menu_slug'],
        'add_media_page' => [3 => 'menu_slug'],
        'add_menu_page' => [3 => 'menu_slug'],
        'add_object_page' => [3 => 'menu_slug'],
        'add_options_page' => [3 => 'menu_slug'],
        'add_pages_page' => [3 => 'menu_slug'],
        'add_plugins_page' => [3 => 'menu_slug'],
        'add_posts_page' => [3 => 'menu_slug'],
        'add_submenu_page' => [0 => 'parent_slug', 4 => 'menu_slug'],
        'add_theme_page' => [3 => 'menu_slug'],
        'add_users_page' => [3 => 'menu_slug'],
        'add_utility_page' => [3 => 'menu_slug'],
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/plugin-menu-slug',
            name: 'Plugin menu slug',
            description: 'Reports __FILE__ passed as the slug or parent-slug argument of add_menu_page() and the other WP Admin menu-registration functions. __FILE__ leaks the plugin\'s filesystem path in the resulting admin URL.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(self::SLOTS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        foreach (self::SLOTS[$name] as $index => $parameter) {
            $value = $this->argument($context, $call, $index, $parameter);
            if ($value === null) {
                continue;
            }

            $constant = self::findFileConstant($context->file, $value);
            if ($constant === null) {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    'Using __FILE__ for menu slugs risks exposing filesystem structure.',
                    $constant->span,
                )->withHelp('Pass a plugin-specific slug string instead of __FILE__.'),
                [self::SNIFF . '.Using__FILE__'],
            );
        }
    }

    private static function findFileConstant(SourceFile $file, Node $node): ?Node
    {
        // getDescendants() excludes the root, which is itself `__FILE__` for a bare argument.
        foreach ([$node, ...$file->getDescendants($node, NodeKind::MagicConstant)] as $candidate) {
            if ($candidate->kind === NodeKind::MagicConstant && strtolower($file->getText($candidate)) === '__file__') {
                return $candidate;
            }
        }

        return null;
    }
}
