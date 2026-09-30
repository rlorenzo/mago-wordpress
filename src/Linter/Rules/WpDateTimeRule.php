<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function preg_match;
use function str_starts_with;
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

    public function __construct(
        private readonly Report $report,
    ) {}

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
            $this->inspectCurrentTime($context, $call);

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

        $this->report->issue(
            $context,
            Issue::new(
                "`{$name}()` conflicts with how WordPress manages timezones.",
                $context->node->span,
                "`{$name}()` uses the runtime timezone, not the WordPress site timezone",
            )->withNote($details['reason'])->withHelp($details['help']),
            [self::SNIFF . '.' . ($name === 'date' ? 'date_date' : "timezone_change_{$name}")],
        );
    }

    /**
     * Reports a `current_time()` call whose first argument is the literal
     * `'timestamp'` or `'U'`. A `$gmt` of literal `true`/`1` asks for a UTC
     * timestamp, which `time()` gives directly; any other `$gmt` yields a
     * "local" pseudo-timestamp.
     */
    private function inspectCurrentTime(LintContext $context, CallExpression $call): void
    {
        $format = $this->argument($context, $call, 0, 'type');
        if ($format === null) {
            return;
        }

        $formatValue = $this->formatValue($context, $format);
        if ($formatValue !== 'timestamp' && $formatValue !== 'U') {
            return;
        }

        $gmt = $this->argument($context, $call, 1, 'gmt');
        $gmtValue = $gmt === null ? null : trim($context->file->getText($gmt));
        if ($gmtValue === 'true' || $gmtValue === '1') {
            $issue = Issue::new(
                '`current_time()` should not be used to retrieve a Unix (UTC) timestamp.',
                $context->node->span,
                'Use `time()` instead',
            )->withHelp('Replace this call with `time()`.');

            // Fixable, as in the sniff, unless the call holds a comment the fix would drop.
            $span = $context->node->span;
            if (!Calls::hasComment($context->file, $span)) {
                $leadingSlash = str_starts_with($context->file->getText($context->node), '\\') ? '\\' : '';
                $issue = $issue->withEdit(TextEdit::replace($span, $leadingSlash . 'time()'));
            }

            $this->report->issue($context, $issue, ['WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC']);

            return;
        }

        $this->report->issue(
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
     * Returns the trimmed value of a literal string or a heredoc/nowdoc
     * without interpolation, as WPCS reads the `$type` argument.
     */
    private function formatValue(LintContext $context, Node $format): ?string
    {
        $value = Values::literalString($context->file, Values::unwrap($context->file, $format));
        if ($value !== null) {
            return trim($value);
        }

        $matches = [];
        if (
            preg_match(
                '/^<<<\s*([\'"]?)(\w+)\1\r?\n([^$]*?)\r?\n\s*\2$/',
                trim($context->file->getText($format)),
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return trim($matches[3]);
    }
}
