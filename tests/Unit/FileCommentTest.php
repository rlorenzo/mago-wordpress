<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;
use function substr_count;

/**
 * Runs `generic/file-comment` through a real worker. The corpus cannot hold these: a `@mago-expect`
 * pragma after the open tag is itself the first comment, so it would change the result.
 * The fixture file is `<?php`, a blank line, then the code, so `SpacingAfterOpen` fires for a docblock.
 */
final class FileCommentTest extends TestCase
{
    use TempProject;

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function cases(): iterable
    {
        yield 'no comment' => ['class A {}', ['Missing file doc comment']];

        yield 'a // comment' => ['// Not a docblock.', ['You must use "/**" style comments']];

        yield 'a /* comment' => ["/* Not a docblock. */\nclass A {}", ['You must use "/**" style comments']];

        yield 'a phpcs annotation is no comment' => ['// phpcs:disable Foo', ['Missing file doc comment']];

        yield 'a docblock that documents the class' => [
            "/**\n * Class A.\n *\n * @package X\n */\nclass A {}",
            ['Missing file doc comment'],
        ];

        yield 'a docblock that documents the function, past an attribute' => [
            "/**\n * Doc.\n */\n#[Foo]\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'a docblock that documents an enum with a long name' => [
            "/**\n * Status.\n */\nenum Status {}",
            ['Missing file doc comment'],
        ];

        yield 'a docblock that documents an enum with a comment before its name' => [
            "/**\n * Status.\n */\nenum /* values */ Status {}",
            ['Missing file doc comment'],
        ];

        yield 'a docblock that documents a function past an attribute with a bracket in a hash comment' => [
            "/**\n * Doc.\n */\n#[Foo( 1, # ]\n2 )]\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'a call to a function named enum past a comment is no declaration' => [
            "/**\n * File.\n *\n * @package X\n */\n\nenum /* explanation */ (\$value);",
            ['There must be no blank lines before the file comment'],
        ];

        yield 'a docblock that documents a function past an attribute with a bracket in a heredoc' => [
            "/**\n * Doc.\n */\n#[Label( <<<EOT\n]\nEOT )]\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'a docblock that documents a function past an attribute with a non-ASCII heredoc label' => [
            "/**\n * Doc.\n */\n#[Label( <<<Donn\u{e9}es\n]\nDonn\u{e9}es )]\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'a call to a function whose name starts with a keyword and a non-ASCII letter is no declaration' => [
            "/**\n * File.\n *\n * @package X\n */\n\nclass\u{e9}();",
            ['There must be no blank lines before the file comment'],
        ];

        yield 'a phpcs instruction padded with many spaces is no comment' => [
            '// ' . str_repeat(' ', 40) . "phpcs:disable Foo.Bar\n\n/**\n * Doc.\n */\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'a qualified call to a function named enum is no declaration' => [
            "/**\n * File.\n *\n * @package X\n */\n\nenum\\run();",
            ['There must be no blank lines before the file comment'],
        ];

        yield 'a docblock that documents a function past an attribute with a bracket in a string' => [
            "/**\n * Doc.\n */\n#[Label( ']' )]\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'a docblock that documents a function past an attribute with an opening bracket in a string' => [
            "/**\n * Doc.\n */\n#[Label( '[' )]\nfunction a() {}",
            ['Missing file doc comment'],
        ];

        yield 'spacing, and a missing @package' => [
            "/**\n * File.\n */\nnamespace X;",
            [
                'There must be no blank lines before the file comment',
                'There must be exactly one blank line after the file comment',
                'Missing @package tag in file comment',
            ],
        ];

        yield 'duplicate and empty tags' => [
            "/**\n * File.\n *\n * @package X\n * @package Y\n * @author\n * @subpackage\n */\n\nnamespace X;",
            [
                'There must be no blank lines before the file comment',
                'Only one @package tag is allowed in a file comment',
                'Content missing for @author tag in file comment',
                'Content missing for @subpackage tag in file comment',
            ],
        ];

        yield 'extra stars before the closer leave the tag empty' => [
            "/**\n * File.\n *\n * @package **/\n\nnamespace X;",
            [
                'There must be no blank lines before the file comment',
                'Content missing for @package tag in file comment',
            ],
        ];

        yield 'a well-formed comment only trips the blank line the fixture adds' => [
            "/**\n * File.\n *\n * @package X\n */\n\nnamespace X;",
            ['There must be no blank lines before the file comment'],
        ];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('cases')]
    public function testReports(string $code, array $messages): void
    {
        [, $output] = $this->lint('generic/file-comment', [], $code, '--reporting-format emacs');

        foreach ($messages as $message) {
            self::assertStringContainsString($message, $output);
        }

        self::assertSame(count($messages), substr_count($output, 'generic/file-comment:'), $output);
    }
}
