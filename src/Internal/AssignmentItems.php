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
 * The first equals sign of a constant, `static` or property declaration, and the numbers the
 * alignment sniff reads off an assignment: where the variable ends, how long the operator is and
 * how much space comes before it.
 *
 * @internal
 */
final class AssignmentItems
{
    private function __construct() {}

    /**
     * @return null|array{int, Span} the end of the name or variable and the `=`
     */
    public static function first(SourceFile $file, Node $declaration): ?array
    {
        $text = $file->contents;
        foreach ($file->getDescendants($declaration, NodeKind::LocalIdentifier) as $name) {
            if ($declaration->kind === NodeKind::Constant || $declaration->kind === NodeKind::ClassLikeConstant) {
                return self::equals($text, $name->span->end);
            }
        }

        foreach ($file->getDescendants($declaration, NodeKind::DirectVariable) as $variable) {
            $item = $file->getParent($variable);
            if (
                $item !== null
                && in_array($item->kind, [NodeKind::PropertyConcreteItem, NodeKind::StaticConcreteItem], strict: true)
            ) {
                return self::equals($text, $variable->span->end);
            }
        }

        return null;
    }

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
        $newline = $offset === 0 ? false : strrpos(substr($text, 0, $offset), "\n");

        return $newline === false ? 0 : $newline + 1;
    }
}
