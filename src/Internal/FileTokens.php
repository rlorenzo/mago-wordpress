<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;
use PhpToken;

/**
 * PHP's own tokens for the whole file being linted, tokenized once for every rule that needs them.
 * The tokens are shared: a caller must not change them.
 *
 * @internal
 */
final class FileTokens
{
    private function __construct() {}

    /** @return list<PhpToken> */
    public static function raw(SourceFile $file): array
    {
        return FileCache::remember($file, 'php-tokens', static fn(): array => PhpToken::tokenize($file->contents));
    }
}
