<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strtolower;
use function trim;

/**
 * The `Modifier` children of a class-like or constant declaration, and the fix that swaps two of
 * them.
 *
 * @internal
 */
final class ModifierOrder
{
    private function __construct() {}

    /**
     * @return array<string, Node> the modifiers by lowercase keyword
     */
    public static function modifiers(SourceFile $file, Node $declaration): array
    {
        $modifiers = [];
        foreach ($file->getChildren($declaration) as $child) {
            if ($child->kind !== NodeKind::Modifier) {
                continue;
            }

            $modifiers[strtolower($file->getText($child))] = $child;
        }

        return $modifiers;
    }

    /**
     * An edit that writes the two modifiers in the other order, or NULL when something other
     * than whitespace sits between them.
     */
    public static function swap(SourceFile $file, Node $first, Node $second): ?TextEdit
    {
        $between = new Span($first->span->end, $second->span->start);
        if (trim($file->getText($between)) !== '') {
            return null;
        }

        return TextEdit::replace(
            new Span($first->span->start, $second->span->end),
            $file->getText($second) . ' ' . $file->getText($first),
        );
    }
}
