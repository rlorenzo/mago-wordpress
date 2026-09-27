<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function ord;
use function strcspn;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

use const PHP_INT_MAX;

/**
 * Ports `WordPress.WP.CapitalPDangit`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class CapitalPDangitRule implements Rule
{
    private const CORRECT_SPELLING = 'WordPress';

    private const LOWERCASE_SPELLING = 'wordpress';

    private const SPACED_SPELLING = 'word press';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/capital-p-dangit',
            name: 'Capital P dangit',
            description: 'Detects the misspelling of WordPress (such as Wordpress, wordPress, or Word Press) in string literals and comments. The correct spelling uses a capital W and a capital P. All-lowercase "wordpress" is never flagged, since it is legitimate in slugs, URLs and identifiers, and occurrences inside URLs or class-like tokens (e.g. Wordpress_Plugin) are ignored too.',
            defaultLevel: Level::Note,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::LiteralString, NodeKind::LiteralStringPart],
        );
    }

    public function lint(LintContext $context): void
    {
        match ($context->node->kind) {
            NodeKind::Program => $this->scanTrivia($context),
            NodeKind::LiteralString, NodeKind::LiteralStringPart => $this->scanText(
                $context,
                $context->file->getText($context->node),
                $context->node->span,
            ),
            default => null,
        };
    }

    private function scanTrivia(LintContext $context): void
    {
        foreach ($context->file->getTrivia() as $trivia) {
            $this->scanText($context, $context->file->getText($trivia->span), $trivia->span);
        }
    }

    private function scanText(LintContext $context, string $text, Span $span): void
    {
        $lower = strtolower($text);
        $this->scanFor($context, $text, $lower, $span, self::LOWERCASE_SPELLING);
        $this->scanFor($context, $text, $lower, $span, self::SPACED_SPELLING);
    }

    private function scanFor(LintContext $context, string $text, string $lower, Span $span, string $needle): void
    {
        // The all-lowercase spelling is legitimate in slugs, URLs and identifiers, and never flagged.
        $exemptCorrect = $needle === self::LOWERCASE_SPELLING;

        $length = strlen($needle);
        $offset = 0;
        $urls = self::urlRanges($lower);
        $url = 0;
        while (($start = strpos($lower, $needle, $offset)) !== false) {
            $end = $start + $length;
            $offset = $end;
            $word = substr($text, $start, $length);

            if ($exemptCorrect && ($word === self::CORRECT_SPELLING || $word === self::LOWERCASE_SPELLING)) {
                continue;
            }

            // Matches arrive in order, so the URL cursor only moves forward.
            while (($urls[$url][1] ?? PHP_INT_MAX) <= $start) {
                ++$url;
            }

            $insideUrl = ($urls[$url][0] ?? PHP_INT_MAX) <= $start;
            if ($insideUrl || !self::isStandaloneWord($text, $start, $end)) {
                continue;
            }

            $this->report($context, $word, $span);
        }
    }

    /**
     * The match is standalone when the characters directly before and after
     * it are non-word bytes or the edge of the text. A `.` joins the match
     * only when a word byte sits on its far side, so `Wordpress.org` is
     * part of a domain while a sentence-ending `Wordpress.` is standalone.
     */
    private static function isStandaloneWord(string $text, int $start, int $end): bool
    {
        $beforeOk = $start === 0 || !self::joinsWord($text, $start - 1, $start - 2);
        $afterOk = $end === strlen($text) || !self::joinsWord($text, $end, $end + 1);

        return $beforeOk && $afterOk;
    }

    private static function joinsWord(string $text, int $adjacent, int $beyond): bool
    {
        if ($text[$adjacent] !== '.') {
            return self::isWordByte($text[$adjacent]);
        }

        return $beyond >= 0 && $beyond < strlen($text) && self::isWordByte($text[$beyond]);
    }

    /**
     * Returns the `[start, end)` ranges that follow a URL scheme separator
     * (`://`) up to the end of its whitespace-delimited token.
     *
     * @return list<array{int, int}>
     */
    private static function urlRanges(string $lower): array
    {
        $ranges = [];
        $offset = 0;
        while (($separator = strpos($lower, needle: '://', offset: $offset)) !== false) {
            $start = $separator + 3;
            $offset = $start + strcspn($lower, characters: " \t\n\v\f\r", offset: $start);
            $ranges[] = [$start, $offset];
        }

        return $ranges;
    }

    /**
     * Besides alphanumerics, `_`, `-`, `/` and `=` count as word bytes, so
     * slugs, URLs, file paths, query strings and class-like tokens
     * (e.g. `Wordpress_Plugin`) are never flagged.
     */
    private static function isWordByte(string $byte): bool
    {
        $code = ord($byte);

        return (
            $code >= 0x30
            && $code <= 0x39
            || $code >= 0x41
            && $code <= 0x5a
            || $code >= 0x61
            && $code <= 0x7a
            || $byte === '_'
            || $byte === '-'
            || $byte === '/'
            || $byte === '='
        );
    }

    private function report(LintContext $context, string $word, Span $span): void
    {
        $context->report(Issue::new('Misspelled `WordPress`', $span, "`{$word}` should be `WordPress`")->withNote(
            'The correct spelling of `WordPress` uses a capital `W` and a capital `P`.',
        )->withHelp('Replace the misspelling with `WordPress`.'));
    }
}
