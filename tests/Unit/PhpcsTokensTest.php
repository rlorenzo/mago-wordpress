<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;

use function array_keys;
use function array_map;
use function count;

final class PhpcsTokensTest extends TestCase
{
    public function testSplitsNamesAsPhpcsDoes(): void
    {
        $tokens = PhpcsTokens::build('<?php \Foo\bar(); \baz();');

        self::assertSame(
            ['ignored', 'ns', 'name', 'ns', 'name', '(', ')', ';', 'ns', 'name', '(', ')', ';'],
            array_map($tokens->type(...), array_keys($tokens->tokens)),
        );
        self::assertTrue($tokens->isNamespaced(4));
        self::assertFalse($tokens->isNamespaced(9));
    }

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

    public function testPairsBracketsPastAnAttribute(): void
    {
        $tokens = PhpcsTokens::build('<?php function f( #[A] $x ) { $y = [ 1 ]; $x[0]; }');
        $open = 3;

        self::assertSame('(', $tokens->code($open));
        self::assertSame(')', $tokens->code((int) $tokens->closer($open)));
        $short = self::find($tokens, '[', 0);
        self::assertTrue($tokens->isShortArray($short));
        self::assertTrue($tokens->isShortArray((int) $tokens->closer($short)));
        self::assertFalse($tokens->isShortArray(self::find($tokens, '[', $short + 1)));
    }

    public function testInlineHtmlQuoteDoesNotOpenAString(): void
    {
        $tokens = PhpcsTokens::build('<?php $a = 1; ?>"<?php $wpdb->query( $q );');

        self::assertSame(';', $tokens->type(5));
        self::assertSame('html', $tokens->type(6));
        self::assertSame('$wpdb', $tokens->content(self::find($tokens, 'var', 6)));
    }

    public function testEmbedTextsStayInsideTheirString(): void
    {
        $tokens = PhpcsTokens::build('<?php $s = "a $b[1] {$c->d} $e->f"; $x = "blog[$id][$key]"; $wpdb;');
        $first = self::find($tokens, 'string', 0);
        $second = self::find($tokens, 'string', $first + 1);

        self::assertSame(
            ['$b[1]', '{$c->d}', '$e->f'],
            array_map(static fn(array $e): string => $e[0], $tokens->embedTexts($first)),
        );
        self::assertSame(
            ['$id', '$key'],
            array_map(static fn(array $e): string => $e[0], $tokens->embedTexts($second)),
        );
        self::assertSame('$wpdb', $tokens->content(self::find($tokens, 'var', $second + 1)));
    }

    private static function find(PhpcsTokens $tokens, string $type, int $from): int
    {
        for ($index = $from; $index < count($tokens->tokens); $index++) {
            if ($tokens->type($index) === $type) {
                return $index;
            }
        }

        self::fail("no {$type} token after {$from}");
    }
}
