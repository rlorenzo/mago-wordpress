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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SpacesFixer;

use function str_contains;
use function strlen;
use function strpos;
use function strspn;

/**
 * Ports `WordPress.Arrays.ArrayKeySpacingRestrictions`: the key of an array access has one space
 * inside each bracket (`$a[ $i ]`), none when it is a lone string or integer literal, with an
 * optional sign (`$a['key']`, `$a[-1]`), and none in `$a[]`. Fixes as phpcbf does.
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ArrayKeySpacingRestrictionsRule implements Rule
{
    private const SNIFF = 'WordPress.Arrays.ArrayKeySpacingRestrictions';

    private const SPACE = " \t\r\n";

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/array-key-spacing-restrictions',
            name: 'Array key spacing',
            description: 'Requires one space inside the brackets of an array key, none around a lone string or integer key.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        foreach ($tokens->codes as $index => $code) {
            if ($code !== '[') {
                continue;
            }

            $closer = $tokens->closer($index);
            if ($closer !== null && $closer > $index) {
                $this->bracket($context, $tokens, $tokens->pos($index), $tokens->pos($closer));
            }
        }
    }

    private function bracket(LintContext $context, PhpcsTokens $tokens, int $open, int $close): void
    {
        $text = $context->file->contents;
        $opener = new Span($open, $open + 1);
        $first = $open + 1 + strspn($text, self::SPACE, $open + 1);
        if ($first === $close) {
            SpacesFixer::check(
                $context,
                $this->report,
                $open + 1,
                $close,
                0,
                'There should be %1$s between the square brackets for an array assignment without an explicit key. Found: %2$s',
                self::SNIFF . '.SpacesBetweenBrackets',
                $opener,
            );

            return;
        }

        $needsSpaces = !$this->isBare($tokens, $text, $first, $close);
        $spaceAfter = strpos(self::SPACE, $text[$open + 1]) !== false;
        $spaceBefore = strpos(self::SPACE, $text[$close - 1]) !== false;
        if ($needsSpaces && (!$spaceAfter || !$spaceBefore)) {
            $issue = Issue::new(
                'Array keys must be surrounded by spaces unless they contain a string or an integer.',
                $opener,
            );
            if (!$spaceAfter) {
                $issue = $issue->withEdit(TextEdit::insert($open + 1, ' '));
            }

            if (!$spaceBefore) {
                $issue = $issue->withEdit(TextEdit::insert($close, ' '));
            }

            $this->report->issue($context, $issue, [self::SNIFF . '.NoSpacesAroundArrayKeys']);
        } elseif (!$needsSpaces && ($spaceAfter || $spaceBefore)) {
            $issue = Issue::new(
                'Array keys must NOT be surrounded by spaces if they only contain a string or an integer.',
                $opener,
            );
            if ($spaceAfter) {
                $issue = $issue->withEdit(TextEdit::delete(new Span($open + 1, $first)));
            }

            if ($spaceBefore) {
                $start = $close;
                while (strpos(self::SPACE, $text[$start - 1]) !== false) {
                    --$start;
                }

                $issue = $issue->withEdit(TextEdit::delete(new Span($start, $close)));
            }

            $this->report->issue($context, $issue, [self::SNIFF . '.SpacesAroundArrayKeys']);
        }

        if (!$needsSpaces) {
            return;
        }

        if ($spaceAfter) {
            SpacesFixer::check(
                $context,
                $this->report,
                $open + 1,
                $first,
                1,
                'There should be exactly %1$s before the array key. Found: %2$s',
                self::SNIFF . '.TooMuchSpaceBeforeKey',
                $opener,
            );
        }

        if ($spaceBefore) {
            $last = $close;
            while (strpos(self::SPACE, $text[$last - 1]) !== false) {
                --$last;
            }

            SpacesFixer::check(
                $context,
                $this->report,
                $last,
                $close,
                1,
                'There should be exactly %1$s after the array key. Found: %2$s',
                self::SNIFF . '.TooMuchSpaceAfterKey',
                new Span($last - 1, $last),
            );
        }
    }

    /** Whether the key, from offset $first, is a lone string or integer literal after an optional sign. */
    private function isBare(PhpcsTokens $tokens, string $text, int $first, int $close): bool
    {
        $at = $tokens->indexAt($first);
        if ($at === null) {
            return false; // a comment
        }

        if ($tokens->code($at) === '-' || $tokens->code($at) === '+') {
            $from = $first + 1;
            $at = $tokens->indexAt($from + strspn($text, self::SPACE, $from));
            if ($at === null) {
                return false;
            }
        }

        $content = $tokens->content($at);
        if (
            !($tokens->code($at) === 'T_CONSTANT_ENCAPSED_STRING' || $tokens->code($at) === 'T_LNUMBER')
            || str_contains($content, "\n")
        ) {
            return false;
        }

        $end = $tokens->pos($at) + strlen($content);

        return ($end + strspn($text, self::SPACE, $end)) === $close;
    }
}
