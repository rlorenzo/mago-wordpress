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
use Rlorenzo\MagoWordPress\Internal\Report;

use function ltrim;
use function str_contains;
use function str_replace;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * Ports `Generic.Strings.UnnecessaryHeredoc`: a heredoc with nothing interpolated and no
 * escape sequence a nowdoc would lose. The fix is phpcbf's: quote the identifier with `'`
 * and unescape `\$` and `\\` in the body.
 */
final class UnnecessaryHeredocRule implements Rule
{
    private const CODE = 'Generic.Strings.UnnecessaryHeredoc.Found';

    /** The escapes a nowdoc does not support, as the sniff lists them. */
    private const ESCAPES = [
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
        '\t',
        '\v',
        '\e',
        '\f',
        '\x',
        '\u',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/unnecessary-heredoc',
            name: 'Unnecessary heredoc',
            description: 'Reports a heredoc with nothing interpolated; use a nowdoc.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::DocumentString],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $text = $context->getText();
        $openerEnd = strpos($text, needle: "\n");
        $closerStart = strrpos($text, needle: "\n");
        if ($openerEnd === false || $closerStart === false) {
            return;
        }

        $opener = substr($text, offset: 0, length: $openerEnd);
        $identifier = trim(ltrim($opener, characters: '<'));
        if (str_contains($identifier, "'")) {
            return; // already a nowdoc
        }

        foreach ($file->getChildren($context->node) as $part) {
            if ($file->getChildren($part)[0]?->kind !== NodeKind::LiteralStringPart) {
                return; // interpolates
            }
        }

        $body = substr($text, $openerEnd + 1, $closerStart - $openerEnd - 1);
        foreach (self::ESCAPES as $escape) {
            if (str_contains($body, $escape)) {
                return;
            }
        }

        $start = $context->node->span->start;
        $issue = Issue::new(
            'Detected heredoc without interpolation or expressions. Use nowdoc syntax instead',
            new Span($start, $start + $openerEnd),
            'heredoc',
        )->withHelp('Use a nowdoc: quote the identifier with single quotes.')->withEdit(TextEdit::replace(
            new Span($start, $start + $openerEnd),
            str_replace($identifier, "'" . trim($identifier, characters: '"') . "'", $opener),
        ));
        $unescaped = str_replace(['\\$', '\\\\'], ['$', '\\'], $body);
        if ($unescaped !== $body) {
            $issue = $issue->withEdit(TextEdit::replace(
                new Span($start + $openerEnd + 1, $start + $closerStart),
                $unescaped,
            ));
        }

        $this->report->issue($context, $issue, [self::CODE]);
    }
}
