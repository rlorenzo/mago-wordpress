<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Ports `Squiz.Strings.DoubleQuoteUsage.NotRequired`, the one code WordPress-Core includes:
 * a double-quoted string with nothing to interpolate and none of the escapes (or a `'`)
 * that need double quotes. The fix is phpcbf's: the same text in single quotes.
 */
final class DoubleQuoteUsageRule implements Rule
{
    private const CODE = 'Squiz.Strings.DoubleQuoteUsage.NotRequired';

    /** The sniff's list, compared as written: two-character escapes, and `'`. */
    private const ALLOWED = [
        '\0',
        '\1',
        '\2',
        '\3',
        '\4',
        '\5',
        '\6',
        '\7',
        '\n',
        '\r',
        '\f',
        '\t',
        '\v',
        '\x',
        '\b',
        '\e',
        '\u',
        '\'',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/double-quote-usage',
            name: 'Double quote usage',
            description: 'Reports a double-quoted string that needs no double quotes; use single quotes.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::LiteralString],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->getText();
        if (strlen($text) < 2 || !str_starts_with($text, '"') || !str_ends_with($text, '"')) {
            return;
        }

        foreach (self::ALLOWED as $allowed) {
            if (str_contains($text, $allowed)) {
                return;
            }
        }

        $inner = str_replace(['\"', '\$'], ['"', '$'], substr($text, offset: 1, length: -1));
        $shown = str_replace(["\r", "\n"], ['\r', '\n'], $text);
        $this->report->issue(
            $context,
            Issue::new(
                "String {$shown} does not require double quotes; use single quotes instead",
                $context->node->span,
                'double quotes not needed',
            )->withHelp('Use single quotes.')->withEdit(TextEdit::replace($context->node->span, "'{$inner}'")),
            [self::CODE],
        );
    }
}
