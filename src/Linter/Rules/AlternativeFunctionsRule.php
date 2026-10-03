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
use function ltrim;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Ports `WordPress.WP.AlternativeFunctions`.
 *
 * Reports PHP functions that have a WordPress alternative (`Lists::ALTERNATIVE_FUNCTIONS`)
 * once the `minimum-wp-version` has the alternative, with the sniff's exceptions:
 * `strip_tags()` with allowed tags, `parse_url()` with a component before WordPress 4.7,
 * `file_get_contents()` on a local file, and local data streams.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AlternativeFunctionsRule extends CallRule
{
    private const SNIFF = 'WordPress.WP.AlternativeFunctions';

    private const LOCAL_STREAMS = [
        'php://input' => true,
        'php://output' => true,
        'php://stdin' => true,
        'php://stdout' => true,
        'php://stderr' => true,
    ];

    private const LOCAL_STREAM_CONSTANTS = ['STDIN' => true, 'STDOUT' => true, 'STDERR' => true];

    /** @var null|array<string, string> function => group */
    private ?array $groups = null;

    public function __construct(
        private readonly Report $report,
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/alternative-functions',
            name: 'Alternative functions',
            description: 'Reports PHP functions that have a WordPress alternative (cURL, parse_url(), json_encode(), file_get_contents(), direct filesystem calls, strip_tags(), rand()), once the configured minimum WordPress version has the alternative.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall, NodeKind::FunctionPartialApplication, NodeKind::TypedUseItemSequence],
        );
    }

    protected function names(): array
    {
        return array_keys($this->groups());
    }

    protected function prefixes(): array
    {
        return ['curl_'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $this->check($context, $context->node, $name, $call);
    }

    protected function inspectReference(LintContext $context, Node $reference, string $name): void
    {
        $this->check($context, $reference, $name, null);
    }

    private function check(LintContext $context, Node $node, string $name, ?CallExpression $call): void
    {
        // The `curl` group allows curl_version().
        $group =
            $this->groups()[$name] ?? ($name !== 'curl_version' && str_starts_with($name, 'curl_') ? 'curl' : null);
        if ($group === null || $this->report->excludesGroup(self::SNIFF, $group)) {
            return;
        }

        $minimum = $this->settings->normalizedMinimumWpVersion();
        $details = Lists::ALTERNATIVE_FUNCTIONS[$group];
        if (!WpVersion::reached($minimum, $details['since'] ?? '0') || $this->isException($context, $name, $call)) {
            return;
        }

        $this->report->issue($context, Issue::new(sprintf($details['message'], $name), $node->span), [
            self::SNIFF . '.' . (string) preg_replace('/[^a-z0-9_]/i', replacement: '_', subject: "{$group}_{$name}"),
        ]);
    }

    /**
     * The sniff's per-function exceptions. A reference (`use function`, `foo(...)`) has no arguments.
     */
    private function isException(LintContext $context, string $name, ?CallExpression $call): bool
    {
        $argument = fn(int $index, string $parameter): ?string => $call === null
            ? null
            : $this->clean($context, $this->argument($context, $call, $index, $parameter));

        switch ($name) {
            case 'strip_tags':
                // wp_strip_all_tags() takes no allowed tags.
                return $argument(1, parameter: 'allowed_tags') !== null;
            case 'parse_url':
                // wp_parse_url() takes a component from WordPress 4.7.
                return (
                    $argument(1, parameter: 'component') !== null
                    && !WpVersion::reached($this->settings->normalizedMinimumWpVersion(), '4.7.0')
                );
            case 'file_get_contents':
                $filename = $argument(0, parameter: 'filename');
                if ($argument(1, parameter: 'use_include_path') === 'true' || $filename === null) {
                    return true;
                }

                if (str_contains($filename, 'http:') || str_contains($filename, 'https:')) {
                    return false;
                }

                return (
                    preg_match(
                        '`(?<!->|::)\b(?:ABSPATH|WP_(?:CONTENT|PLUGIN)_DIR|WPMU_PLUGIN_DIR|TEMPLATEPATH|STYLESHEETPATH|(?:MU)?PLUGINDIR)\b`',
                        $filename,
                    ) === 1
                    || preg_match(
                        '`(?<!->|::)(?:get_home_path|plugin_dir_path|get_(?:stylesheet|template)_directory|wp_upload_dir)\s*\(`i',
                        $filename,
                    ) === 1
                    || self::isLocalDataStream($filename)
                );
            case 'file_put_contents':
            case 'fopen':
            case 'readfile':
                $filename = $argument(0, parameter: 'filename');

                return $filename !== null && self::isLocalDataStream($filename);
            default:
                return false;
        }
    }

    private static function isLocalDataStream(string $clean): bool
    {
        // TextStrings::stripQuotes(): one pair of matching outer quotes.
        $stripped = preg_replace('`^([\'"])(.*)\1$`Ds', replacement: '$2', subject: $clean) ?? $clean;
        if (
            (self::LOCAL_STREAMS[$stripped] ?? false)
            || (self::LOCAL_STREAM_CONSTANTS[ltrim($clean, characters: '\\')] ?? false)
        ) {
            return true;
        }

        return str_starts_with($stripped, 'php://temp/') || str_starts_with($stripped, 'php://fd/');
    }

    /**
     * The argument's source text without comments, as PHPCSUtils' `clean` parameter value.
     */
    private function clean(LintContext $context, ?Node $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $file = $context->file;
        $text = '';
        $from = $value->span->start;
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->span->start < $from || $trivia->span->end > $value->span->end) {
                continue;
            }

            $text .= substr($file->contents, $from, $trivia->span->start - $from);
            $from = $trivia->span->end;
        }

        return trim($text . substr($file->contents, $from, $value->span->end - $from));
    }

    /**
     * @return array<string, string>
     */
    private function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $groups = [];
        foreach (Lists::ALTERNATIVE_FUNCTIONS as $group => $details) {
            foreach ($details['functions'] as $function) {
                // The `curl_*` wildcard is prefixes().
                if (str_contains($function, '*')) {
                    continue;
                }

                $groups[$function] = $group;
            }
        }

        return $this->groups = $groups;
    }
}
