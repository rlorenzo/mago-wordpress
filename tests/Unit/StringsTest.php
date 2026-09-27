<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\Strings;

final class StringsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function literals(): iterable
    {
        yield 'single-quoted escapes' => ["'it\\'s a \\\\ and \\n'", "it's a \\ and \\n"];
        yield 'single-quoted keeps a quote at the edge' => ["'\\''", "'"];
        yield 'binary prefix' => ["b'bytes'", 'bytes'];
        yield 'empty' => ["''", ''];
        yield 'double-quoted control escapes' => ['"\n\t\r\v\e\f"', "\n\t\r\x0B\x1B\x0C"];
        yield 'double-quoted literal escapes' => ['"\$\"\\\\"', '$"\\'];
        yield 'double-quoted keeps unknown escapes' => ['"\q\\\'"', '\q\\\''];
        yield 'hex escape' => ['"\x41\x7a"', 'Az'];
        yield 'octal escape' => ['"\101\0"', "A\0"];
        yield 'unicode escape' => ['"\u{e9}\u{1F600}"', "\u{e9}\u{1F600}"];
        yield 'escaped dollar is not interpolation' => ['"\$name"', '$name'];
        yield 'lone dollar is not interpolation' => ['"costs $5"', 'costs $5'];
        yield 'interpolated variable' => ['"hello $name"', null];
        yield 'interpolated expression' => ['"hello {$name}"', null];
        yield 'interpolated dollar-brace' => ['"hello ${name}"', null];
        yield 'escaped dollar-brace is not interpolation' => ['"\${name}"', '${name}'];
        yield 'nowdoc is raw' => ["<<<'EOT'\n  a\\n \$b\n  EOT", "a\\n \$b"];
        yield 'heredoc decodes escapes' => ["<<<EOT\n\\x41\\tb\nEOT", "A\tb"];
        yield 'quoted heredoc label' => ["<<<\"EOT\"\n    one\n      two\n    EOT", "one\n  two"];
        yield 'empty heredoc' => ["<<<EOT\nEOT", ''];
        yield 'interpolated heredoc' => ["<<<EOT\n\$x\nEOT", null];
        yield 'not a string' => ['42', null];
    }

    #[DataProvider('literals')]
    public function testUnquote(string $raw, ?string $expected): void
    {
        self::assertSame($expected, Strings::unquote($raw));
    }
}
