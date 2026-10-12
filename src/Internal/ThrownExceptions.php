<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_push;
use function array_unique;
use function array_values;
use function in_array;

/**
 * The exception classes a function throws as `Squiz.Commenting.FunctionCommentThrowTag` reads
 * them from the function's phpcs tokens.
 *
 * @internal
 */
final class ThrownExceptions
{
    private function __construct() {}

    /**
     * @return null|array{exceptions: list<string>, unknown: int} NULL when nothing is thrown
     */
    public static function of(PhpcsTokens $tokens, int $function, int $end): ?array
    {
        $exceptions = [];
        $unknown = 0;
        $found = false;
        $position = $function;
        while (true) {
            $position = ThrowScan::nextScope($tokens, $position + 1, $end);
            if ($position === null) {
                break;
            }

            if ($tokens->code($position) !== 'T_THROW') {
                $position = $tokens->scopeCloser($position) ?? $end;
                if ($position >= $end) {
                    break;
                }

                continue;
            }

            $found = true;
            [$names, $extra] = ThrowSubject::of($tokens, $function, $position, $end);
            array_push($exceptions, ...$names);
            $unknown += $extra;

            if ($position >= $end) {
                break;
            }
        }

        return $found ? ['exceptions' => $exceptions, 'unknown' => $unknown] : null;
    }

    /**
     * What the function body throws, or NULL when it has no body or throws nothing.
     *
     * @return null|array{exceptions: list<string>, unknown: int}
     */
    public static function forFunction(PhpcsTokens $tokens, Node $function, SourceFile $file): ?array
    {
        $keyword = null;
        foreach ($file->getChildren($function) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                $keyword = $child->span->start;

                break;
            }
        }

        $index = $keyword === null ? null : $tokens->indexAt($keyword);
        $end = $index === null ? null : $tokens->scopeCloser($index);
        if ($index === null || $end === null) {
            return null;
        }

        $found = self::of($tokens, $index, $end);

        return (
            $found === null
                ? null
                : ['exceptions' => array_values(array_unique($found['exceptions'])), 'unknown' => $found['unknown']]
        );
    }
}
