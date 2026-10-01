<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Closure;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Values derived from the file being linted, kept until the worker moves on
 * to the next file.
 *
 * A worker lints one file at a time, so one slot is enough, and a value is
 * recomputed if files ever interleave. This replaces a `WeakMap` per cache:
 * with several WeakMaps keyed by the same `SourceFile`, PHP 8.4's cycle
 * collector intermittently corrupted the heap (`zend_mm_heap corrupted`) on
 * large codebases. That crash is gone with this single strong slot.
 *
 * @internal
 */
final class FileCache
{
    private static ?SourceFile $file = null;

    /** @var array<string, mixed> */
    private static array $values = [];

    private function __construct() {}

    /**
     * Returns the value cached under $key for $file, computing it on first use.
     *
     * @template T
     *
     * @param Closure(): T $compute
     *
     * @return T
     */
    public static function remember(SourceFile $file, string $key, Closure $compute): mixed
    {
        if (self::$file !== $file) {
            self::$file = $file;
            self::$values = [];
        }

        /** @var T */
        return self::$values[$key] ??= $compute();
    }
}
