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
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_keys;
use function array_unique;
use function array_values;

/**
 * Ports `WordPress.WP.DiscouragedFunctions` (and widens it with
 * `PHP.DiscouragedPHPFunctions` and `PHP.DevelopmentFunctions`).
 *
 * Six WordPress functions get a specific reason and alternative. Every other
 * function in `Lists::DISCOURAGED_PHP_FUNCTION_GROUPS` and
 * `Lists::DEVELOPMENT_FUNCTIONS` is reported with its WPCS group name.
 */
final class DiscouragedWpFunctionsRule extends CallRule
{
    private const SNIFF = 'WordPress.WP.DiscouragedFunctions';

    private const DISCOURAGED_FUNCTIONS = [
        'query_posts' => [
            'reason' => '`query_posts()` replaces and breaks the main query, causing pagination and conditional tag issues.',
            'alternative' => 'Use a new `WP_Query` instance, or modify the main query via the `pre_get_posts` filter.',
        ],
        'wp_reset_query' => [
            'reason' => '`wp_reset_query()` is only needed after `query_posts()`, which should not be used.',
            'alternative' => 'Use `wp_reset_postdata()` after custom `WP_Query` loops instead.',
        ],
        'get_page_by_title' => [
            'reason' => '`get_page_by_title()` is deprecated and discouraged.',
            'alternative' => 'Use a `WP_Query` with the `title` argument instead.',
        ],
        'url_to_postid' => [
            'reason' => '`url_to_postid()` runs an expensive query on every call.',
            'alternative' => 'Cache the result (e.g. with the object cache or a transient) instead of calling it repeatedly.',
        ],
        'attachment_url_to_postid' => [
            'reason' => '`attachment_url_to_postid()` runs an expensive query on every call.',
            'alternative' => 'Cache the result (e.g. with the object cache or a transient) instead of calling it repeatedly.',
        ],
        'wp_is_mobile' => [
            'reason' => '`wp_is_mobile()` relies on unreliable user-agent sniffing and breaks with page caching.',
            'alternative' => 'Use client-side detection (CSS media queries or JavaScript), or server checks that are safe with caching.',
        ],
    ];

    /** @var array<string, array{note: string, help: string}> */
    private const GROUP_MESSAGES = [
        'obfuscation' => [
            'note' => 'These functions are frequently used to obfuscate malicious code and are restricted by many hosts.',
            'help' => 'Avoid obfuscation functions; write code that stays readable without decoding.',
        ],
        'runtime_configuration' => [
            'note' => 'Changing PHP runtime configuration from a plugin or theme is unreliable and can affect other code running in the same request.',
            'help' => 'Configure the server environment instead of changing it at runtime.',
        ],
        'serialize' => [
            'note' => '`serialize()`/`unserialize()` can execute arbitrary code on untrusted input via PHP object injection.',
            'help' => 'Use `wp_json_encode()`/`json_decode()` for data interchange instead.',
        ],
        'system_calls' => [
            'note' => 'Shelling out from a plugin or theme is a common remote-code-execution vector and is restricted by many hosts.',
            'help' => 'Avoid invoking system commands; use a PHP-native alternative instead.',
        ],
        'urlencode' => [
            'note' => '`urlencode()` encodes spaces as `+`, which is only valid in a query string, not in a URL path.',
            'help' => 'Use `rawurlencode()` for anything other than an `application/x-www-form-urlencoded` query string value.',
        ],
        'development' => [
            'note' => 'This function is meant for development and debugging, not production code.',
            'help' => 'Remove this call before shipping, or gate it behind a debug constant such as `WP_DEBUG`.',
        ],
    ];

    /** @var null|array<string, string> */
    private ?array $groupsByFunction = null;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/discouraged-wp-functions',
            name: 'Discouraged WordPress functions',
            description: 'Reports calls to WordPress functions that are discouraged because they break the main query, are deprecated, are expensive without caching, or rely on unreliable user-agent sniffing, plus calls in the wider WPCS-restricted PHP function groups (obfuscation, runtime configuration, serialize, system calls, urlencode) and development/debugging functions.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall, NodeKind::FunctionPartialApplication, NodeKind::TypedUseItemSequence],
        );
    }

    protected function names(): array
    {
        return array_values(array_unique([
            ...array_keys(self::DISCOURAGED_FUNCTIONS),
            ...array_keys($this->groupedFunctions()),
        ]));
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $this->inspectReference($context, $context->node, $name);
    }

    protected function inspectReference(LintContext $context, Node $reference, string $name): void
    {
        $details = self::DISCOURAGED_FUNCTIONS[$name] ?? null;
        if ($details !== null) {
            // Each WPCS group here is named after its one function.
            if ($this->report->excludesGroup(self::SNIFF, $name)) {
                return;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    "Discouraged WordPress function `{$name}()`",
                    $reference->span,
                    "`{$name}()` is discouraged",
                )->withNote($details['reason'])->withHelp($details['alternative']),
                [self::SNIFF . ".{$name}_{$name}"],
            );

            return;
        }

        $group = $this->groupedFunctions()[$name] ?? null;
        if ($group === null) {
            return;
        }

        [$sniff, $wpcsGroup] = self::wpcsGroup($group, $name);
        if ($this->report->excludesGroup($sniff, $wpcsGroup)) {
            return;
        }

        $messages = self::GROUP_MESSAGES[$group];
        $this->report->issue(
            $context,
            Issue::new(
                "Discouraged PHP function `{$name}()` ({$group})",
                $reference->span,
                "`{$name}()` is discouraged by the WordPress Coding Standards \"{$group}\" function group",
            )->withNote($messages['note'])->withHelp($messages['help']),
            ["{$sniff}.{$wpcsGroup}_{$name}"],
        );
    }

    /**
     * The WPCS sniff and group that report the function: the message code joins the
     * group and the function name.
     *
     * @return array{string, string}
     */
    private static function wpcsGroup(string $group, string $name): array
    {
        if ($group !== 'development') {
            return ['WordPress.PHP.DiscouragedPHPFunctions', $group];
        }

        return [
            'WordPress.PHP.DevelopmentFunctions',
            $name === 'error_reporting' || $name === 'phpinfo' ? 'prevent_path_disclosure' : 'error_log',
        ];
    }

    /**
     * @return array<string, string> function name => WPCS group name
     */
    private function groupedFunctions(): array
    {
        if ($this->groupsByFunction !== null) {
            return $this->groupsByFunction;
        }

        $groups = Lists::DISCOURAGED_PHP_FUNCTION_GROUPS;
        $groups['development'] = Lists::DEVELOPMENT_FUNCTIONS;

        $map = [];
        foreach ($groups as $group => $functionNames) {
            foreach ($functionNames as $functionName) {
                $map[$functionName] ??= $group;
            }
        }

        return $this->groupsByFunction = $map;
    }
}
