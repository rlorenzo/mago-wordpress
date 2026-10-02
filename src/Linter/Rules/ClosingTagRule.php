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

use function count;
use function in_array;
use function preg_match;
use function rtrim;
use function strlen;
use function substr;
use function trim;

/**
 * Ports `PSR2.Files.ClosingTag`: a PHP-only file (no inline HTML other than whitespace, and a
 * `<?php` or `<?` open tag) that ends in `?>`. The fix, as phpcbf's, replaces the tag (and the
 * newline it swallows) with a newline and ends the last statement with `;` when it needs one.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ClosingTagRule implements Rule
{
    private const CODE = 'PSR2.Files.ClosingTag.NotAllowed';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/closing-tag',
            name: 'Closing tag',
            description: 'Reports a `?>` closing tag at the end of a file that holds only PHP; output after it is sent by accident.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if ($file->getNodes(NodeKind::FullOpeningTag) === [] && $file->getNodes(NodeKind::ShortOpeningTag) === []) {
            return;
        }

        foreach ($file->getNodes(NodeKind::Inline) as $inline) {
            if (trim($file->getText($inline)) !== '') {
                return;
            }
        }

        $tags = $file->getNodes(NodeKind::ClosingTag);
        $last = null;
        foreach ($tags as $tag) {
            $last = $last === null || $tag->span->start > $last->span->start ? $tag : $last;
        }

        if ($last === null || trim(substr($file->contents, $last->span->end)) !== '') {
            return;
        }

        $contents = $file->contents;
        $end = $last->span->end;
        $eol = preg_match('/\r\n?|\n/', $contents, $match) === 1 ? $match[0] : "\n";
        $end += preg_match('/\G(\r\n|\n|\r)/', $contents, $newline, offset: $end) === 1 ? strlen($newline[1]) : 0;
        $issue = Issue::new(
            'A closing tag is not permitted at the end of a PHP file',
            $last->span,
            'closing tag',
        )->withHelp('Remove the closing tag.')->withEdit(TextEdit::replace(new Span($last->span->start, $end), $eol));

        $code = $this->previousCodeEnd($context, $last->span->start);
        $before = rtrim(substr($contents, 0, $code));
        $needsSemicolon =
            !in_array(substr($before, -1), [';', '}'], strict: true) && preg_match('/<\?(php)?$/i', $before) !== 1;
        if ($needsSemicolon) {
            $issue = $issue->withEdit(TextEdit::insert($code, ';'));
        }

        $this->report->issue($context, $issue, [self::CODE]);
    }

    /** The end of the last code before $offset, skipping whitespace and comments. */
    private function previousCodeEnd(LintContext $context, int $offset): int
    {
        $trivia = $context->file->getTrivia();
        $position = strlen(rtrim(substr($context->file->contents, 0, $offset)));
        $contents = $context->file->contents;
        for ($i = count($trivia) - 1; $i >= 0; $i--) {
            if ($trivia[$i]->span->start >= $position) {
                continue;
            }

            if (strlen(rtrim(substr($contents, 0, $trivia[$i]->span->end))) !== $position) {
                break;
            }

            $position = strlen(rtrim(substr($contents, 0, $trivia[$i]->span->start)));
        }

        return $position;
    }
}
