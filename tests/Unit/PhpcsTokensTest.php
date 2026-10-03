<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;

final class PhpcsTokensTest extends TestCase
{
    public function testAttributeBracketDoesNotCloseTheEnclosingShortArray(): void
    {
        $tokens = PhpcsTokens::build('<?php $a = [#[A] fn() => 1, $_GET["x"]];');
        $open = null;
        for ($index = 0; $tokens->code($index) !== null; $index++) {
            if ($tokens->code($index) === 'T_OPEN_SHORT_ARRAY') {
                $open = $index;
            }
        }

        self::assertNotNull($open);
        self::assertSame('T_CLOSE_SHORT_ARRAY', $tokens->code((int) $tokens->closer($open)));
        self::assertSame(';', $tokens->code((int) $tokens->closer($open) + 1));
    }
}
