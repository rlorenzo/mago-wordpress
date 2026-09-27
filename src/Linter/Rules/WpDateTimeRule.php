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
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function strtolower;
use function trim;

/**
 * Ports `WordPress.DateTime.RestrictedFunctions` and
 * `WordPress.DateTime.CurrentTimeTimestamp`.
 *
 * `Lists::WP_DATETIME_RESTRICTED` also lists `date_default_timezone_set()`,
 * which the restricted-functions check reports too.
 */
final class WpDateTimeRule extends CallRule
{
    private const SNIFF = 'WordPress.DateTime.RestrictedFunctions';

    private const CURRENT_TIME_FUNCTION = 'current_time';

    /** @var array<string, array{reason: string, help: string}> */
    private const RESTRICTED_MESSAGES = [
        'date' => [
            'reason' => 'WordPress manages its own timezone setting, which `date()` does not respect.',
            'help' => "Use `gmdate()` for timezone-independent output, or `wp_date()` for the site's timezone.",
        ],
        'date_default_timezone_set' => [
            'reason' => 'WordPress manages its own timezone setting; changing the PHP runtime default timezone conflicts with it and can produce inconsistent results across requests.',
            'help' => 'Do not change the runtime default timezone. Configure the site timezone under Settings > General instead.',
        ],
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/wp-date-time',
            name: 'WordPress date time',
            description: "Detects date/time handling that conflicts with how WordPress manages timezones: date() and date_default_timezone_set() depend on the runtime timezone, not the WordPress site timezone, and current_time('timestamp') (or current_time('U')) returns a \"local\" pseudo-timestamp shifted by the site's UTC offset instead of a true Unix timestamp.",
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return [...Lists::WP_DATETIME_RESTRICTED, self::CURRENT_TIME_FUNCTION];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        if ($name === self::CURRENT_TIME_FUNCTION) {
            if ($this->isTimestampRetrieval($context, $call)) {
                $this->reportCurrentTimeTimestamp($context);
            }

            return;
        }

        $this->reportRestrictedFunction($context, $name);
    }

    private function reportRestrictedFunction(LintContext $context, string $name): void
    {
        $details = self::RESTRICTED_MESSAGES[$name] ?? null;
        if ($details === null) {
            return;
        }

        Report::issue(
            $context,
            Issue::new(
                "`{$name}()` conflicts with how WordPress manages timezones.",
                $context->node->span,
                "`{$name}()` uses the runtime timezone, not the WordPress site timezone",
            )->withNote($details['reason'])->withHelp($details['help']),
            [self::SNIFF . '.' . ($name === 'date' ? 'date_date' : "timezone_change_{$name}")],
        );
    }

    private function reportCurrentTimeTimestamp(LintContext $context): void
    {
        Report::issue(
            $context,
            Issue::new(
                '`current_time()` should not be used to retrieve a timestamp.',
                $context->node->span,
                'This returns a "local" pseudo-timestamp offset from UTC',
            )->withNote(
                "`current_time('timestamp')` returns a Unix timestamp shifted by the site's UTC offset, which corrupts date arithmetic.",
            )->withHelp('Use `time()` for a true Unix timestamp, or `current_datetime()` for a timezone-aware object.'),
            ['WordPress.DateTime.CurrentTimeTimestamp.Requested'],
        );
    }

    /**
     * Whether a `current_time()` call retrieves a (pseudo-)timestamp: the
     * first argument is the literal `'timestamp'` or `'U'`, and the `$gmt`
     * argument is absent or a literal `false`/`0`.
     */
    private function isTimestampRetrieval(LintContext $context, CallExpression $call): bool
    {
        $format = $this->argument($context, $call, 0, 'type');
        if ($format === null) {
            return false;
        }

        $formatValue = Values::literalString($context->file, $format);
        if ($formatValue === null || $formatValue !== 'timestamp' && $formatValue !== 'U') {
            return false;
        }

        $gmt = $this->argument($context, $call, 1, 'gmt');
        if ($gmt === null) {
            return true;
        }

        $gmtValue = strtolower(trim($context->file->getText($gmt)));

        return $gmtValue === 'false' || $gmtValue === '0';
    }
}
