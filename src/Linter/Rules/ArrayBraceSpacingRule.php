<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\ArrayShape;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SpacesFixer;

use function strpos;
use function strspn;
use function substr;

/**
 * Ports `NormalizedArrays.Arrays.ArrayBraceSpacing` as WordPress-Core sets it: no space between
 * `array` and `(` or inside an empty array, one space inside a single-line array, and a line break
 * after the opener and before the closer of a multi-line one (a comment on the opener's line may
 * come first). Fixes as phpcbf does. The sniff's four properties are not configurable here.
 */
final class ArrayBraceSpacingRule implements Rule
{
    private const SNIFF = 'NormalizedArrays.Arrays.ArrayBraceSpacing';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/array-brace-spacing',
            name: 'Array brace spacing',
            description: 'Checks the space after the array opener and before the closer: none when empty, one when single-line, a line break when multi-line, as WordPress-Core does.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach ([NodeKind::LegacyArray, NodeKind::Array] as $kind) {
            foreach (NodeIndex::ofKind($context->file, $context->node, $kind) as $array) {
                $this->array($context, $array);
            }
        }
    }

    private function array(LintContext $context, Node $array): void
    {
        $file = $context->file;
        $shape = ArrayShape::of($file, $array);
        if ($shape === null) {
            return;
        }

        $text = $file->contents;
        ['open' => $open, 'close' => $close] = $shape;
        if ($array->kind === NodeKind::LegacyArray) {
            $keyword = $array->span->start;
            SpacesFixer::check(
                $context,
                $this->report,
                $keyword + 5,
                $open,
                0,
                'There should be %1$s between the "array" keyword and the open parenthesis. Found: %2$s',
                self::SNIFF . '.SpaceAfterKeyword',
                new Span($keyword, $keyword + 5),
            );
        }

        $next = $open + 1 + strspn($text, " \t\r\n", $open + 1);
        $opener = new Span($open, $open + 1);
        $closer = new Span($close, $close + 1);
        if ($next === $close) {
            SpacesFixer::check(
                $context,
                $this->report,
                $open + 1,
                $close,
                0,
                'There should be %1$s between the array opener and closer for an empty array. Found: %2$s',
                self::SNIFF . '.EmptyArraySpacing',
                $opener,
            );

            return;
        }

        $before = $close;
        while ($before > $open && strpos(" \t\r\n", $text[$before - 1]) !== false) {
            --$before;
        }

        if (LineIndex::of($file, $open) === LineIndex::of($file, $close)) {
            SpacesFixer::check(
                $context,
                $this->report,
                $open + 1,
                $next,
                1,
                'Expected %1$s after the array opener in a single line array. Found: %2$s',
                self::SNIFF . '.SpaceAfterArrayOpenerSingleLine',
                $opener,
            );
            SpacesFixer::check(
                $context,
                $this->report,
                $before,
                $close,
                1,
                'Expected %1$s before the array closer in a single line array. Found: %2$s',
                self::SNIFF . '.SpaceBeforeArrayCloserSingleLine',
                $closer,
            );

            return;
        }

        $aEnd = $open + 1;
        $at = $opener;
        $commentEnd = self::lineCommentEnd($text, $next);
        if ($commentEnd !== null && LineIndex::of($file, $next) === LineIndex::of($file, $open)) {
            $at = new Span($next, $commentEnd);
            $aEnd = $commentEnd;
            $next = $aEnd + strspn($text, " \t\r\n", $aEnd);
        }

        SpacesFixer::check(
            $context,
            $this->report,
            $aEnd,
            $next,
            'newline',
            'Expected %1$s after the array opener in a multi line array. Found: %2$s',
            self::SNIFF . '.SpaceAfterArrayOpenerMultiLine',
            $at,
        );
        SpacesFixer::check(
            $context,
            $this->report,
            $before,
            $close,
            'newline',
            'Expected %1$s before the array closer in a multi line array. Found: %2$s',
            self::SNIFF . '.SpaceBeforeArrayCloserMultiLine',
            $closer,
        );
    }

    /** The end of the T_COMMENT (not a docblock) starting at $offset. */
    private static function lineCommentEnd(string $text, int $offset): ?int
    {
        if (substr($text, $offset, 3) === '/**' && substr($text, $offset, 4) !== '/**/') {
            return null;
        }

        return SpacesFixer::commentEnd($text, $offset);
    }
}
