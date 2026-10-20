<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function rtrim;
use function sprintf;
use function str_contains;
use function strlen;
use function substr_count;

use const T_INLINE_HTML;
use const T_OPEN_TAG;
use const T_OPEN_TAG_WITH_ECHO;

/**
 * Ports `PSR2.Files.EndFileNewline`: a file of PHP ends in exactly one line break (`NoneFound` when it
 * has none, `TooMany` for blank lines after the last line of code). A file with inline HTML after its first
 * PHP tag is skipped, as in the sniff.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class EndFileNewlineRule implements Rule
{
    private const SNIFF = 'PSR2.Files.EndFileNewline';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/end-file-newline',
            name: 'End of file newline',
            description: 'Reports a file that does not end in a line break, or ends in blank lines.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        $length = strlen($text);
        if ($length === 0 || !str_contains($text, '<?')) {
            return;
        }

        $endsWithNewline = $text[$length - 1] === "\n";
        // The usual file ends in one line break after something that is not whitespace: nothing to report.
        if ($endsWithNewline && !self::isBlank($text[$length - 2] ?? 'x')) {
            return;
        }

        if (str_contains($text, '?>') && self::hasHtmlAfterOpenTag($text)) {
            return;
        }

        if (!$endsWithNewline) {
            $this->report->issue(
                $context,
                Issue::new(
                    'Expected 1 newline at end of file; 0 found',
                    new Span($length - 1, $length),
                )->withEdit(TextEdit::insert($length, "\n")),
                [self::SNIFF . '.NoneFound'],
            );

            return;
        }

        $code = rtrim($text, " \t\r\n\v\f");
        $codeEnd = strlen($code);
        $blankLines = substr_count($text, "\n", $codeEnd);
        if ($blankLines <= 1 || $codeEnd === 0) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                sprintf('Expected 1 blank line at end of file; %d found', $blankLines),
                new Span($codeEnd - 1, $codeEnd),
            )->withEdit(TextEdit::replace(new Span($codeEnd, $length), "\n")),
            [self::SNIFF . '.TooMany'],
        );
    }

    private static function isBlank(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\r" || $char === "\n";
    }

    private static function hasHtmlAfterOpenTag(string $text): bool
    {
        $open = false;
        foreach (PhpToken::tokenize($text) as $token) {
            if ($token->id === T_OPEN_TAG || $token->id === T_OPEN_TAG_WITH_ECHO) {
                $open = true;
            } elseif ($open && $token->id === T_INLINE_HTML) {
                return true;
            }
        }

        return false;
    }
}
