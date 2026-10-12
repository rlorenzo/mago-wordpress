<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * Where the items of an array go once `WordPress.Arrays.ArrayDeclarationSpacing` is satisfied.
 *
 * @internal
 */
final class ArrayLayout
{
    private function __construct() {}

    /**
     * @param array{open: int, close: int, items: list<array{start: int, end: int, keyed: bool}>} $shape
     */
    public static function breaksFirst(SourceFile $file, array $shape): bool
    {
        $items = $shape['items'];
        if (LineIndex::of($file, $shape['open']) !== LineIndex::of($file, $shape['close'])) {
            return (
                strpos(substr($file->contents, $shape['open'] + 1, $items[0]['start'] - $shape['open'] - 1), "\n")
                === false
            );
        }

        if (count($items) < 2) {
            return false;
        }

        foreach ($items as $item) {
            if ($item['keyed']) {
                return true;
            }
        }

        return false;
    }

    /** How many arrays around this one, opening on its line, put their first item on a line of its own. */
    public static function depth(SourceFile $file, Node $array, int $open): int
    {
        $depth = 0;
        $line = LineIndex::of($file, $open);
        for ($parent = $file->getParent($array); $parent !== null; $parent = $file->getParent($parent)) {
            if (!in_array($parent->kind, [NodeKind::Array, NodeKind::LegacyArray], strict: true)) {
                continue;
            }

            $shape = ArrayShape::of($file, $parent);
            if (
                $shape !== null
                && $shape['items'] !== []
                && LineIndex::of($file, $shape['open']) === $line
                && self::breaksFirst($file, $shape)
            ) {
                ++$depth;
            }
        }

        return $depth;
    }

    /**
     * @param array{open: int, close: int, items: list<array{start: int, end: int, keyed: bool}>} $shape
     */
    public static function itemIndent(string $text, array $shape, string $base): string
    {
        foreach ($shape['items'] as $item) {
            $before = substr($text, 0, $item['start']);
            $newline = strrpos($before, "\n");
            $lineStart = $newline === false ? 0 : $newline + 1;
            $lead = substr($text, $lineStart, $item['start'] - $lineStart);
            if (trim($lead) === '' && $lineStart > $shape['open']) {
                return $lead;
            }
        }

        return $base . "\t";
    }
}
