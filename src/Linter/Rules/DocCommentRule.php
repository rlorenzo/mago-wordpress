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
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\DocBlockChecks;
use Rlorenzo\MagoWordPress\Internal\DocBlockFixes;
use Rlorenzo\MagoWordPress\Internal\DocBlockScan;
use Rlorenzo\MagoWordPress\Internal\DocBlockTokens;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function in_array;
use function max;
use function preg_match;
use function str_repeat;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * Ports `Generic.Commenting.DocComment` as `WordPress-Docs` sets it, for every docblock:
 * `Empty`, `MissingShort`, `ShortNotCapital`, `LongNotCapital`, `SpacingBetween` (short and long
 * description), `SpacingBeforeTags`, `SpacingAfterTagGroup` and `SpacingAfter` (blank lines at the
 * end), with phpcbf's fixes for the four spacing codes.
 *
 * `WordPress-Docs` turns off `ContentAfterOpen`, `ContentBeforeClose`, `SpacingBeforeShort`,
 * `TagValueIndent`, `ParamGroup`, `ParamNotFirst`, `NonParamGroup` and `TagsNotGrouped`; this
 * rule does not report them.
 */
final class DocCommentRule implements Rule
{
    private const SNIFF = 'Generic.Commenting.DocComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/doc-comment',
            name: 'Doc comment',
            description: 'Reports an empty docblock, one without a short description or whose descriptions start in lower case, and wrong blank lines between its parts.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $text = $file->getText($trivia->span);
            if (strlen($text) < 5 || strpos($text, '*/', 3) === false) {
                continue;
            }

            $tokens = DocBlockTokens::tokenize($text, $trivia->span->start, LineIndex::of($file, $trivia->span->start));
            $this->check($context, $tokens, $file->contents);
        }
    }

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    private function check(LintContext $context, array $t, string $source): void
    {
        $close = count($t) - 1;
        if ($t[$close]['code'] !== 'close') {
            return;
        }

        $short = DocBlockScan::next($t, 1, $close);
        if ($short === null) {
            $this->add($context, 'Doc comment is empty', DocBlockScan::span($t[0]), 'Empty');

            return;
        }

        $prev = DocBlockScan::previous($t, $close - 1, 0, ['ws', 'star'], match: false);
        if ($t[$prev]['line'] < ($t[$close]['line'] - 1)) {
            $this->add(
                $context,
                'Additional blank lines found at end of doc comment',
                DocBlockScan::span($t[$close]),
                'SpacingAfter',
                DocBlockFixes::removal($t, $prev + 1, $close, $t[$close]['line']),
            );
        }

        if ($t[$short]['code'] !== 'string') {
            $this->add($context, 'Missing short description in doc comment', DocBlockScan::span($t[0]), 'MissingShort');
        } else {
            $this->addAll($context, DocBlockChecks::descriptions($t, $short, $close));
        }

        $this->addAll($context, DocBlockChecks::tags($t, $close, $source));
    }

    /**
     * @param list<array{string, Span, string, null|TextEdit}> $findings
     */
    private function addAll(LintContext $context, array $findings): void
    {
        foreach ($findings as [$message, $span, $code, $edit]) {
            $this->add($context, $message, $span, $code, $edit);
        }
    }

    private function add(LintContext $context, string $message, Span $span, string $code, ?TextEdit $edit = null): void
    {
        $issue = Issue::new($message, $span);
        if ($edit !== null) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }
}
