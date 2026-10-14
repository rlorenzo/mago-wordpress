<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function strpos;

/**
 * An array literal as `WordPress.Arrays.ArrayDeclarationSpacing` sees it: the offsets of its opening
 * and closing bracket, and of each item (its key and value) with whether the item has a key.
 *
 * @internal
 */
final class ArrayShape
{
    private const TRANSPARENT = [
        NodeKind::Expression,
        NodeKind::ArrayElement,
        NodeKind::ValueArrayElement,
        NodeKind::KeyValueArrayElement,
        NodeKind::Array,
        NodeKind::LegacyArray,
    ];

    private function __construct() {}

    /**
     * @return null|array{open: int, close: int, items: list<array{start: int, end: int, keyed: bool}>}
     */
    public static function of(SourceFile $file, Node $array): ?array
    {
        if (self::isList($file, $array)) {
            return null;
        }

        $text = $file->contents;
        $close = $array->span->end - 1;
        if ($array->kind === NodeKind::Array) {
            $open = $array->span->start;
        } else {
            $open = strpos($text, '(', $array->span->start);
            if ($open === false) {
                return null;
            }
        }

        $items = [];
        foreach ($file->getChildren($array) as $element) {
            $pair = $file->getChildren($element)[0] ?? null;
            if ($pair === null || in_array($pair->kind, [NodeKind::MissingArrayElement], strict: true)) {
                continue;
            }

            $items[] = [
                'start' => $element->span->start,
                'end' => $element->span->end,
                'keyed' => $pair->kind === NodeKind::KeyValueArrayElement,
            ];
        }

        return ['open' => $open, 'close' => $close, 'items' => $items];
    }

    /** Whether the array is the target of a destructuring assignment or `foreach` (a short list). */
    public static function isList(SourceFile $file, Node $array): bool
    {
        $child = $array;
        $parent = $file->getParent($child);
        while ($parent !== null && in_array($parent->kind, self::TRANSPARENT, strict: true)) {
            $child = $parent;
            $parent = $file->getParent($child);
        }

        if ($parent === null) {
            return false;
        }

        if (in_array($parent->kind, [NodeKind::ForeachValueTarget, NodeKind::ForeachKeyValueTarget], strict: true)) {
            return true;
        }

        $target = $parent->kind === NodeKind::Assignment ? $file->getChildren($parent)[0] ?? null : null;

        return (
            $target !== null
            && $target->span->start === $child->span->start
            && $target->span->end === $child->span->end
        );
    }
}
