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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;

use function max;
use function ord;
use function str_contains;
use function str_repeat;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function substr_count;
use function trim;

/**
 * Ports `WordPress.Arrays.MultipleStatementAlignment` as WordPress-Core sets it (`maxColumn` 60):
 * in a multi-line array the `=>` of every item whose key ends before column 59 goes to one column,
 * and a longer key gets a single space before its arrow. Single-line arrays get one space before
 * each arrow. `mago format` aligns every arrow however long the key, so this is meant to run after
 * it, as `mago lint --fix --only wordpress/array-alignment` (the third step of
 * `mago-wordpress format`).
 *
 * Columns are counted with a tab width of 4, as the `WordPress` ruleset sets. An item whose key and
 * arrow are on different lines, or with a comment between them, is left out, as `ignoreNewlines`
 * leaves out the first.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ArrayAlignmentRule implements Rule
{
    private const SNIFF = 'WordPress.Arrays.MultipleStatementAlignment';

    private const MAX_COLUMN = 60;

    private const TAB_WIDTH = 4;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/array-alignment',
            name: 'Array alignment',
            description: 'Aligns the double arrows of a multi-line array up to column 60, as WordPress-Core does. `mago-wordpress format` runs its fix after `mago format`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::LegacyArray, NodeKind::Array],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $array = $context->node;
        $parent = $file->getParent($array);
        $grandparent = $parent === null ? null : $file->getParent($parent);
        if (
            $grandparent !== null
            && $grandparent->kind === NodeKind::Assignment
            && ($file->getChildren($grandparent)[0] ?? null) === $parent
        ) {
            // A short list being assigned to, not an array.
            return;
        }

        $items = $this->items($file, $array);
        if ($items === []) {
            return;
        }

        $multiLine = self::line($file, $array->span->start) !== self::line($file, $array->span->end - 1);
        if (!$multiLine) {
            $this->singleLine($context, $items);

            return;
        }

        $columns = [];
        foreach ($items as $item) {
            if (($item['end'] + 2) > self::MAX_COLUMN) {
                continue;
            }

            $columns[] = $item['end'];
        }

        if ($columns === []) {
            return;
        }

        $expected = max($columns) + 2;
        foreach ($items as $item) {
            $this->align($context, $item, $expected);
        }
    }

    /**
     * The items with a double arrow on the line their key ends on, in source order.
     *
     * @return list<array{gap: Span, arrow: int, end: int, column: int, space: string, key: string}>
     */
    private function items(SourceFile $file, Node $array): array
    {
        $items = [];
        foreach ($file->getChildren($array) as $element) {
            $pair = $file->getChildren($element)[0] ?? null;
            if ($pair === null || $pair->kind !== NodeKind::KeyValueArrayElement) {
                continue;
            }

            [$key, $value] = $file->getChildren($pair) + [null, null];
            if ($key === null || $value === null) {
                continue;
            }

            $gap = substr($file->contents, $key->span->end, $value->span->start - $key->span->end);
            $at = strpos($gap, needle: '=>');
            if ($at === false) {
                continue;
            }

            $space = substr($gap, offset: 0, length: $at);
            if (trim($space) !== '' || str_contains($space, "\n")) {
                continue;
            }

            $arrow = $key->span->end + $at;
            $items[] = [
                'gap' => new Span($key->span->end, $arrow),
                'arrow' => $arrow,
                'end' => self::width($file, $key->span->end),
                'column' => self::width($file, $arrow) + 1,
                'space' => $space,
                'key' => $file->getText($key),
            ];
        }

        return $items;
    }

    /** @param list<array{gap: Span, arrow: int, end: int, column: int, space: string, key: string}> $items */
    private function singleLine(LintContext $context, array $items): void
    {
        foreach ($items as $item) {
            if ($item['space'] === '' || $item['space'] === ' ') {
                continue;
            }

            $found = strlen($item['space']);
            $this->fix(
                $context,
                $item,
                1,
                'SpaceBeforeDoubleArrow',
                "Expected 1 space between \"{$item['key']}\" and double arrow; {$found} found",
            );
        }
    }

    /** @param array{gap: Span, arrow: int, end: int, column: int, space: string, key: string} $item */
    private function align(LintContext $context, array $item, int $expected): void
    {
        if ($item['column'] === $expected) {
            return;
        }

        $found = strlen($item['space']);
        if (($item['end'] + 2) > self::MAX_COLUMN) {
            if ($item['column'] === ($item['end'] + 2)) {
                return;
            }

            $this->fix(
                $context,
                $item,
                1,
                $found === 0 ? 'LongIndexNoSpaceBeforeDoubleArrow' : 'LongIndexSpaceBeforeDoubleArrow',
                "Expected 1 space between \"{$item['key']}\" and double arrow; {$found} found.",
            );

            return;
        }

        $spaces = $expected - ($item['end'] + 1);
        $this->fix(
            $context,
            $item,
            $spaces,
            'DoubleArrowNotAligned',
            "Array double arrow not aligned correctly; expected {$spaces} space(s) between \"{$item['key']}\" and double arrow, but found {$found}.",
        );
    }

    /** @param array{gap: Span, arrow: int, end: int, column: int, space: string, key: string} $item */
    private function fix(LintContext $context, array $item, int $spaces, string $code, string $message): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                $message,
                new Span($item['arrow'], $item['arrow'] + 2),
            )->withEdit(TextEdit::replace($item['gap'], str_repeat(' ', max(0, $spaces)))),
            [self::SNIFF . '.' . $code],
        );
    }

    /**
     * The display width of the line's text up to $offset, tabs counted to the next multiple of 4.
     *
     * Items on one very long line ask for increasing offsets, so the last answer is kept and the
     * next one continues from it instead of rescanning the line.
     */
    private static function width(SourceFile $file, int $offset): int
    {
        static $memo = null;

        if (
            $memo !== null
            && $memo[0] === $file
            && $memo[1] <= $offset
            && !str_contains(substr($file->contents, $memo[1], $offset - $memo[1]), "\n")
        ) {
            [, $i, $width] = $memo;
        } else {
            // A negative offset searches backwards from that many bytes before the end: no copy of the text before it.
            $newline = $offset === 0
                ? false
                : strrpos($file->contents, needle: "\n", offset: $offset - strlen($file->contents) - 1);
            $i = $newline === false ? 0 : $newline + 1;
            $width = 0;
        }

        for (; $i < $offset; ++$i) {
            // Characters, not bytes, as phpcs counts (a UTF-8 continuation byte is no column).
            $width += $file->contents[$i] === "\t"
                ? self::TAB_WIDTH - ($width % self::TAB_WIDTH)
                : (int) ((ord($file->contents[$i]) & 0xC0) !== 0x80);
        }
        $memo = [$file, $offset, $width];

        return $width;
    }

    private static function line(SourceFile $file, int $offset): int
    {
        return substr_count($file->contents, needle: "\n", offset: 0, length: $offset);
    }
}
