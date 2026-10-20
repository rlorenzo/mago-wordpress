<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function rtrim;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * The equals signs of a constant, `static` or property declaration, and the numbers the
 * alignment sniff reads off an assignment: where the variable ends, how long the operator is and
 * how much space comes before it.
 *
 * @internal
 */
final class AssignmentItems
{
    private function __construct() {}

    /**
     * @param Span $operator
     *
     * @return array{varEnd: int, length: int, found: int, gap: Span, operator: Span}
     */
    public static function describe(string $text, int $end, Span $operator): array
    {
        $gap = substr($text, $end, $operator->start - $end);
        $plain = trim($gap) === '' && strpos($gap, "\n") === false;
        // With a comment in the gap, only the whitespace right in front of the operator is padded.
        $from = $plain || trim($gap) === '' ? $end : $operator->start - (strlen($gap) - strlen(rtrim($gap)));
        $found = $plain
            ? ArrayText::gapWidth($text, $end, $operator->start)
            : ($from === $end ? 1 : $operator->start - $from);

        return [
            'varEnd' => ArrayText::gapWidth($text, self::lineStart($text, $end), $end) + 1,
            'length' => $operator->end - $operator->start,
            'found' => $found,
            'gap' => new Span($from, $operator->start),
            'operator' => $operator,
        ];
    }

    /**
     * Every item with a default of a constant, `static` or property declaration, in source order.
     *
     * @return list<array{int, Span}> the end of each name or variable and its `=`
     */
    public static function all(SourceFile $file, Node $declaration): array
    {
        $text = $file->contents;
        $found = [];
        $names = $declaration->kind === NodeKind::Constant || $declaration->kind === NodeKind::ClassLikeConstant;
        foreach ($file->getDescendants(
            $declaration,
            $names ? NodeKind::LocalIdentifier : NodeKind::DirectVariable,
        ) as $name) {
            $item = $file->getParent($name);
            if (
                $item === null
                || !in_array(
                    $item->kind,
                    [
                        NodeKind::ConstantItem,
                        NodeKind::ClassLikeConstantItem,
                        NodeKind::PropertyConcreteItem,
                        NodeKind::StaticConcreteItem,
                    ],
                    strict: true,
                )
            ) {
                continue;
            }

            $equals = self::equals($text, $name->span->end);
            if ($equals !== null) {
                $found[] = $equals;
            }
        }

        return $found;
    }

    /**
     * @return null|array{int, Span}
     */
    private static function equals(string $text, int $from): ?array
    {
        // Whitespace and comments may sit between the name and the `=`, as `describe()` expects.
        $at = PhpComments::skipEmpty($text, $from);
        if (($text[$at] ?? '') !== '=') {
            return null;
        }

        return [$from, new Span($at, $at + 1)];
    }

    private static function lineStart(string $text, int $offset): int
    {
        $newline = $offset === 0 ? false : strrpos($text, "\n", $offset - strlen($text) - 1);

        return $newline === false ? 0 : $newline + 1;
    }
}
