<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Strings;

use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_split;
use function rtrim;
use function sort;
use function str_starts_with;
use function strlen;
use function strtolower;

use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Ports `WordPress.WP.CapitalPDangit`.
 *
 * Text is checked line by line, as PHPCS tokenizes multi-line strings,
 * comments and inline HTML into one token per line.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class CapitalPDangitRule implements Rule
{
    private const SNIFF = 'WordPress.WP.CapitalPDangit';

    private const CORRECT_SPELLING = 'WordPress';

    // Verbatim from the sniff: skips URLs, e-mail addresses, variables, paths,
    // file names and dash-joined tokens such as CSS classes.
    private const WP_REGEX = '#(?<![\\\\/\$@`-])\b(Word[ _-]*Pres+)\b(?![@/`-]|\.(?:org|com|net|test|tv)|[^\s<>\'"()]*?\.(?:php|js|css|png|j[e]?pg|gif|pot))#i';

    private const WP_CLASSNAME_REGEX = '`(?:^|_)(Word[_]*Pres+)(?:_|$)`i';

    private const TEXT_KINDS = [
        NodeKind::LiteralString,
        NodeKind::InterpolatedString,
        NodeKind::DocumentString,
        NodeKind::Inline,
    ];

    private const CLASS_LIKE_KINDS = [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum];

    // The sniff skips everything inside these, including comments.
    private const SKIPPED_KINDS = [
        NodeKind::Array,
        NodeKind::LegacyArray,
        NodeKind::List,
        NodeKind::ArrayAccess,
        NodeKind::Constant,
        NodeKind::ClassLikeConstant,
        NodeKind::FunctionCall,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/capital-p-dangit',
            name: 'Capital P dangit',
            description: 'Detects the misspelling of WordPress (such as Wordpress, wordpress, or Word Press) in string literals, inline HTML, comments, and class-like and namespace names. The correct spelling uses a capital W and a capital P. Occurrences inside URLs, paths, file names, e-mail addresses, dash-joined tokens (e.g. fa-wordpress), HTML attribute values, arrays, and constant declarations are ignored.',
            defaultLevel: Level::Note,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $program = $context->node;
        $skipped = self::skippedRanges($file, $program);

        foreach ($file->getTrivia() as $trivia) {
            $this->scanText($context, $trivia->span, $skipped, 'MisspelledInComment');
        }

        foreach (NodeIndex::ofKinds($file, $program, self::TEXT_KINDS) as $node) {
            $this->scanText(
                $context,
                $node->span,
                $skipped,
                'MisspelledInText',
                Strings::interpolatedRanges($file, $node),
            );
        }

        foreach (NodeIndex::ofKinds($file, $program, self::CLASS_LIKE_KINDS) as $node) {
            $name = $file->getFirstDescendant($node, NodeKind::LocalIdentifier);
            if ($name !== null && $file->getParent($name) === $node) {
                $this->checkName($context, $name, [$file->getText($name)], 'MisspelledClassName');
            }
        }

        foreach (NodeIndex::ofKind($file, $program, NodeKind::Namespace) as $node) {
            $name = $file->getChildren($node)[1] ?? null;
            if ($name !== null && $name->kind === NodeKind::Identifier) {
                $this->checkName($context, $name, explode('\\', $file->getText($name)), 'MisspelledNamespaceName');
            }
        }
    }

    /**
     * The merged, sorted `[start, end)` ranges the sniff never reports in:
     * array and list literals, array access keys, constant declarations
     * and the arguments of global define() calls.
     *
     * @return list<array{int, int}>
     */
    private static function skippedRanges(SourceFile $file, Node $program): array
    {
        $ranges = [];
        foreach (NodeIndex::ofKinds($file, $program, self::SKIPPED_KINDS) as $node) {
            $start = self::skippedStart($file, $node);
            if ($start !== null) {
                $ranges[] = [$start, $node->span->end];
            }
        }

        sort($ranges);
        // Node spans nest or are disjoint, so dropping each range inside the last kept one merges them.
        $merged = [];
        $end = -1;
        foreach ($ranges as $range) {
            if ($range[0] < $end) {
                continue;
            }

            $merged[] = $range;
            $end = $range[1];
        }

        return $merged;
    }

    private static function skippedStart(SourceFile $file, Node $node): ?int
    {
        $start = $node->span->start;

        return match ($node->kind) {
            // Only the key, from the `[` on.
            NodeKind::ArrayAccess => ($file->getChildren($node)[0] ?? $node)->span->end,
            NodeKind::FunctionCall => match (strtolower(Calls::leadingIdentifier($file->contents, $start) ?? '')) {
                'define', '\\define' => $start,
                default => null,
            },
            default => $start,
        };
    }

    /**
     * @param list<array{int, int}> $ranges
     */
    private static function isSkipped(array $ranges, int $offset): bool
    {
        $low = 0;
        $high = count($ranges) - 1;
        while ($low <= $high) {
            $mid = ($low + $high) >> 1;
            [$start, $end] = $ranges[$mid];
            if ($offset >= $start && $offset < $end) {
                return true;
            }

            if ($offset < $start) {
                $high = $mid - 1;
                continue;
            }

            $low = $mid + 1;
        }

        return false;
    }

    /**
     * @param list<array{int, int}> $skipped
     * @param list<array{int, int}> $interpolated
     */
    private function scanText(
        LintContext $context,
        Span $span,
        array $skipped,
        string $code,
        array $interpolated = [],
    ): void {
        if (self::isSkipped($skipped, $span->start)) {
            return;
        }

        $text = $context->file->getText($span);
        $docBlock = $code === 'MisspelledInComment' && str_starts_with($text, '/**');
        $inLink = false;
        $tag = [];
        $offset = 0;
        foreach (explode("\n", $text) as $line) {
            $start = $span->start + $offset;
            $offset += strlen($line) + 1;

            // Doc text after an `@link` tag, up to the next tag, is ignored.
            if ($docBlock && preg_match('/^\s*(?:\/\*\*)?\s*\*?\s*(@\S+)/', $line, $tag) === 1) {
                $inLink = $tag[1] === '@link';
            }

            if ($inLink) {
                continue;
            }

            $misspelled = self::misspellings($line);
            if ($misspelled === []) {
                continue;
            }

            // Fixable, as in the sniff: each misspelling becomes `WordPress`. In a string or inline HTML
            // that changes runtime output, so only the comment fix is safe.
            $safety = $code === 'MisspelledInComment' ? Safety::Safe : Safety::PotentiallyUnsafe;
            $edits = [];
            foreach ($misspelled as $wordOffset => $word) {
                $wordSpan = new Span($start + $wordOffset, $start + $wordOffset + strlen($word));
                // The sniff also rewrites a misspelling inside an interpolated `{$...}`, which changes the code.
                if (!self::isSkipped($interpolated, $wordSpan->start)) {
                    $edits[] = TextEdit::replace($wordSpan, self::CORRECT_SPELLING)->withSafety($safety);
                }
            }

            $this->report(
                $context,
                new Span($start, $start + strlen(rtrim($line, characters: "\r"))),
                $misspelled,
                $code,
                $edits,
            );
        }
    }

    /**
     * The misspelled matches in one line, after the sniff's false-positive filters, keyed by
     * their byte offset in the line.
     *
     * @return array<int, string>
     */
    private static function misspellings(string $content): array
    {
        // The whole match is the captured word, so the split alternates text and matches.
        $parts = preg_split(self::WP_REGEX, $content, flags: PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [];
        }

        // Verbatim from the sniff, including searching from the end of the previous match.
        $found = [];
        $offset = 0;
        $end = 0;
        $count = count($parts);
        for ($index = 1; $index < $count; $index += 2) {
            $word = $parts[$index];
            $end += strlen($parts[$index - 1]) + strlen($word);
            $quoted = preg_quote($word, delimiter: '`');
            $falsePositive =
                preg_match('`http[s]?://[^\s<>\'"()]*' . $quoted . '`', $content, offset: $offset) === 1
                || preg_match('`[a-z]+=(["\'])' . $quoted . '\1`', $content, offset: $offset) === 1
                || preg_match('`\\\\\'' . $quoted . '\\\\\'`', $content, offset: $offset) === 1
                || preg_match('`(?:\?|&amp;|&)[a-z0-9_]+=' . $quoted . '(?:&|$)`', $content, offset: $offset) === 1;
            $offset = $end;

            if (!$falsePositive && $word !== self::CORRECT_SPELLING) {
                $found[$end - strlen($word)] = $word;
            }
        }

        return $found;
    }

    /**
     * @param list<string> $levels
     */
    private function checkName(LintContext $context, Node $name, array $levels, string $code): void
    {
        $misspelled = [];
        $matches = [];
        foreach ($levels as $level) {
            preg_match_all(self::WP_CLASSNAME_REGEX, $level, $matches);
            foreach ($matches[1] as $word) {
                if ($word === self::CORRECT_SPELLING) {
                    continue;
                }

                $misspelled[] = $word;
            }
        }

        if ($misspelled !== []) {
            $this->report($context, $name->span, $misspelled, $code);
        }
    }

    /**
     * @param array<string> $misspelled
     * @param list<TextEdit> $edits
     */
    private function report(LintContext $context, Span $span, array $misspelled, string $code, array $edits = []): void
    {
        $found = implode('`, `', $misspelled);
        $issue = Issue::new('Misspelled `WordPress`', $span, "`{$found}` should be `WordPress`")->withNote(
            'The correct spelling of `WordPress` uses a capital `W` and a capital `P`.',
        )->withHelp('Replace the misspelling with `WordPress`.');
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }
}
