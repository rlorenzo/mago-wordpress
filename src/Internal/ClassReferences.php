<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function ltrim;
use function str_contains;

/**
 * Finds class-name identifiers in class-referencing nodes and resolves them
 * to global class names, for rules that match against a WordPress core
 * class table.
 *
 * @internal
 */
final class ClassReferences
{
    /**
     * Node kinds a class name can arrive as, once unwrapped from its
     * `Expression` wrapper.
     */
    private const IDENTIFIER_KINDS = [
        NodeKind::Identifier,
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
    ];

    private function __construct() {}

    /**
     * Unwraps an `Expression` down to a class-name identifier, or returns
     * an empty list for any other expression shape (a variable class,
     * `self`/`parent`/`static`, an anonymous class...).
     *
     * @return list<Node>
     */
    public static function identifier(SourceFile $file, ?Node $node): array
    {
        if ($node === null) {
            return [];
        }

        $node = Values::unwrap($file, $node);

        return in_array($node->kind, self::IDENTIFIER_KINDS, strict: true) ? [$node] : [];
    }

    /**
     * The class names listed in an `extends` or `implements` clause.
     *
     * @return list<Node>
     */
    public static function heritage(SourceFile $file, Node $node): array
    {
        $identifiers = [];
        foreach ($file->getChildren($node) as $child) {
            if (in_array($child->kind, self::IDENTIFIER_KINDS, strict: true)) {
                $identifiers[] = $child;
            }
        }

        return $identifiers;
    }

    /**
     * The identifier's resolved name without its leading `\`, or NULL when
     * it does not resolve to the global namespace. A bare name qualifies
     * against the file's namespace and `use` imports, so a class inside a
     * namespace resolves to `Some\Namespace\ClassName` and is skipped.
     */
    public static function globalName(SourceFile $file, Node $identifier): ?string
    {
        $resolved = $file->getResolvedName($identifier)?->name;
        if ($resolved === null) {
            return null;
        }

        $normalized = ltrim($resolved, characters: '\\');

        return str_contains($normalized, '\\') ? null : $normalized;
    }
}
