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
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\LeadingComment;
use Rlorenzo\MagoWordPress\Internal\PhpText;
use Rlorenzo\MagoWordPress\Internal\Report;

use function max;
use function min;
use function preg_match;
use function strlen;
use function substr;

/**
 * Ports `Squiz.Commenting.ClassComment` as `WordPress-Docs` sets it, for classes (not interfaces,
 * traits or enums, as in the sniff): `SpacingAfter`, the blank lines between the docblock and the
 * class. A class without a docblock (`Missing`) is `missing-docs`' to report.
 *
 * `WordPress-Docs` turns off `TagNotAllowed`; this rule does not report it.
 */
final class ClassCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.ClassComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/class-comment',
            name: 'Class comment',
            description: 'Reports blank lines between a class docblock and the class.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $comment = LeadingComment::before($file, $context->node->span->start);
        if ($comment === null || $comment['kind'] !== TriviaKind::DocBlockComment) {
            return;
        }

        $text = $file->contents;
        $keyword = $context->node->span->start;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                $keyword = $child->span->start;

                break;
            }
        }

        if (PhpText::line($text, self::tokenAbove($text, $keyword) - 1) === (PhpText::line($text, $keyword) - 1)) {
            return;
        }

        $closer = PhpText::docblockCloser($file->getText($comment['span']), 0);
        if ($closer === null) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'There must be no blank lines after the class comment',
                new Span($comment['span']->start + $closer[0], $comment['span']->start + $closer[1]),
            ),
            [self::SNIFF . '.SpacingAfter'],
        );
    }

    /** The end of the token above $offset, past whitespace and the `abstract`, `final` and `readonly` modifiers. */
    private static function tokenAbove(string $text, int $offset): int
    {
        while (true) {
            while ($offset > 0 && preg_match('~\s~', $text[$offset - 1]) === 1) {
                --$offset;
            }

            $match = [];
            $window = substr($text, max(0, $offset - 10), min($offset, 10));
            if (preg_match('~(?:^|[^\w$])(abstract|final|readonly)$~i', $window, $match) !== 1) {
                return $offset;
            }

            $offset -= strlen($match[1]);
        }
    }
}
