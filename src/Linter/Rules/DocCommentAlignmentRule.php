<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\DocBlockAlignment;
use Rlorenzo\MagoWordPress\Internal\DocBlockTokens;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\PhpComments;
use Rlorenzo\MagoWordPress\Internal\Report;

use function preg_match;
use function rtrim;
use function strlen;
use function strpos;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Ports `Squiz.Commenting.DocCommentAlignment` as `WordPress-Docs` sets it, for the docblocks of
 * classes, interfaces, enums, enum cases, functions, methods and properties, and the docblock
 * right after the opening tag: `SpaceBeforeStar` (the stars one column in from the opener) and
 * `NoSpaceAfterStar`, both fixable.
 *
 * `WordPress-Docs` turns off `SpaceAfterStar`; this rule does not report it.
 */
final class DocCommentAlignmentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.DocCommentAlignment';

    /** What a documented declaration starts with (`case` inside an enum is checked separately). */
    private const DECLARATION = '~^(?:public|protected|private|class|interface|enum(?!\s*[(:=;,\\\\])|function|static|abstract|final|var|readonly)(?![\w\x80-\xff])~i';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/doc-comment-alignment',
            name: 'Doc comment alignment',
            description: 'Reports docblock asterisks that are not one column in from the opening tag, and an asterisk not followed by a space.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $text = $file->contents;
        $cases = null;
        $comments = [];
        foreach ($file->getTrivia() as $trivia) {
            $comments[] = [$trivia->span->start, $trivia->span->end];
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $block = $file->getText($trivia->span);
            if (strlen($block) < 5 || strpos($block, '*/', 3) === false) {
                continue;
            }

            $next = PhpComments::skipEmpty($text, $trivia->span->end);
            $cases ??= self::enumCases($context);
            if (!self::documents($text, $next, $cases) && !self::afterOpenTag($text, $trivia->span->start, $comments)) {
                continue;
            }

            $tokens = DocBlockTokens::tokenize(
                $block,
                $trivia->span->start,
                LineIndex::of($file, $trivia->span->start),
            );
            foreach (DocBlockAlignment::findings($tokens, $text) as [$message, $span, $code, $edit]) {
                $issue = Issue::new($message, $span);
                if ($edit !== null) {
                    $issue = $issue->withEdit($edit);
                }

                $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
            }
        }
    }

    /**
     * @param array<int, true> $cases
     */
    private static function documents(string $text, int $next, array $cases): bool
    {
        return preg_match(self::DECLARATION, substr($text, $next, 12)) === 1 || isset($cases[$next]);
    }

    /**
     * Whether the docblock is the first thing after the PHP opening tag (a file comment).
     *
     * @param list<array{int, int}> $comments spans of the comments before the docblock
     */
    private static function afterOpenTag(string $text, int $start, array $comments): bool
    {
        $match = [];
        $trimmed = rtrim(substr($text, 0, $start));
        if (preg_match('~<\?(?:php|=)?$~i', $trimmed, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return false;
        }

        // A `<?php` written inside an earlier comment is not an opening tag.
        foreach ($comments as [$from, $to]) {
            if ($match[0][1] >= $from && $match[0][1] < $to) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, true>
     */
    private static function enumCases(LintContext $context): array
    {
        $cases = [];
        foreach (NodeIndex::ofKind($context->file, $context->node, NodeKind::EnumCase) as $case) {
            $cases[$case->span->start] = true;
        }

        return $cases;
    }
}
