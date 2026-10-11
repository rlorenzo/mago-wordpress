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
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function in_array;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Ports `WordPress.PHP.IniSet`.
 *
 * Every `ini_set()` and `ini_alter()` call is reported. The few options WPCS lets through are
 * skipped, and the options WordPress has a better way to set are errors (`<option>_Disallowed`).
 * The rest are the `Risky` warning, which `SplitRule` serves from `wordpress/ini-set-warning`.
 */
final class IniSetRule extends CallRule
{
    private const SNIFF = 'WordPress.PHP.IniSet';

    /**
     * Options that are safe to change. An entry with `valid` lists the lowercased values that are.
     *
     * @var array<string, list<string>>
     */
    private const SAFE_OPTIONS = [
        'auto_detect_line_endings' => [],
        'highlight.bg' => [],
        'highlight.comment' => [],
        'highlight.default' => [],
        'highlight.html' => [],
        'highlight.keyword' => [],
        'highlight.string' => [],
        'short_open_tag' => ['true', '1', 'on'],
    ];

    /**
     * Options that must not be changed. An entry with `invalid` is disallowed only for those
     * lowercased values.
     *
     * @var array<string, array{message: string, invalid?: list<string>}>
     */
    private const DISALLOWED_OPTIONS = [
        'bcmath.scale' => ['message' => 'Use `bcscale()` instead.'],
        'display_errors' => ['message' => 'Use `WP_DEBUG_DISPLAY` instead.'],
        'error_reporting' => ['message' => 'Use `WP_DEBUG` instead.'],
        'filter.default' => [
            'message' => 'Changing the option value can break other plugins. Use the filter flag constants when calling the Filter functions instead.',
        ],
        'filter.default_flags' => [
            'message' => 'Changing the option value can break other plugins. Use the filter flag constants when calling the Filter functions instead.',
        ],
        'iconv.input_encoding' => [
            'message' => 'This option is not supported since PHP 5.6 - use `iconv_set_encoding()` instead.',
        ],
        'iconv.internal_encoding' => [
            'message' => 'This option is not supported since PHP 5.6 - use `iconv_set_encoding()` instead.',
        ],
        'iconv.output_encoding' => [
            'message' => 'This option is not supported since PHP 5.6 - use `iconv_set_encoding()` instead.',
        ],
        'ignore_user_abort' => ['message' => 'Use `ignore_user_abort()` instead.'],
        'log_errors' => ['message' => 'Use `WP_DEBUG_LOG` instead.'],
        'max_execution_time' => ['message' => 'Use `set_time_limit()` instead.'],
        'memory_limit' => ['message' => 'Use `wp_raise_memory_limit()` or hook into the filters in that function.'],
        'short_open_tag' => [
            'message' => 'Turning off short_open_tag is prohibited as it can break other plugins.',
            'invalid' => ['false', '0', 'off'],
        ],
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/ini-set',
            name: 'Ini set',
            description: 'Reports ini_set() and ini_alter() calls. Options WordPress has its own way to set (memory_limit, display_errors, ...) are errors; other changes are the Risky warning in wordpress/ini-set-warning.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['ini_set', 'ini_alter'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $option = Calls::argumentWithSpread($context->file, $call, 0, 'option');
        $value = Calls::argumentWithSpread($context->file, $call, 1, 'value');
        if ($option === null || $value === null) {
            return;
        }

        $file = $context->file;
        $optionText = trim($file->getText($option));
        $valueText = trim($file->getText($value));
        $optionName = self::stripQuotes($optionText);
        $optionValue = strtolower(self::stripQuotes($valueText));

        $safe = self::SAFE_OPTIONS[$optionName] ?? null;
        if ($safe !== null && ($safe === [] || in_array($optionValue, $safe, strict: true))) {
            return;
        }

        $written = $call->getName($file) ?? $name;
        $found = sprintf('%s(%s, %s)', $written, $optionText, $valueText);
        $disallowed = self::DISALLOWED_OPTIONS[$optionName] ?? null;
        if ($disallowed !== null && in_array($optionValue, $disallowed['invalid'] ?? [$optionValue], strict: true)) {
            $this->report->issue(
                $context,
                Issue::new(
                    "Found: {$found}. {$disallowed['message']}",
                    $context->node->span,
                    "`{$optionName}` must not be changed at runtime",
                ),
                [self::SNIFF . '.' . self::errorCode($optionName . '_Disallowed')],
            );

            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                "Changing configuration values at runtime is strongly discouraged. Found: {$found}",
                $context->node->span,
                'Runtime configuration change',
            ),
            [self::SNIFF . '.Risky'],
        );
    }

    /** The text without one pair of matching quotes, as PHPCSUtils' stripQuotes() does. */
    private static function stripQuotes(string $text): string
    {
        $matches = [];

        return preg_match('/^([\'"])(.*)\1$/s', $text, $matches) === 1 ? $matches[2] : $text;
    }

    /** A message code from free text: every character outside `[A-Za-z0-9_]` becomes an underscore. */
    private static function errorCode(string $text): string
    {
        return (string) preg_replace('/[^a-z0-9_]/i', replacement: '_', subject: $text);
    }
}
