<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_get_contents;

/**
 * Applies a rule's fixes through a real worker with `mago lint --fix` and checks the result
 * against what phpcbf writes for the same code (WPCS's `.inc.fixed` files).
 */
final class RuleFixesTest extends TestCase
{
    use TempProject;

    /**
     * @return iterable<string, array{string, string, string, string, 4?: list<string>}>
     */
    public static function cases(): iterable
    {
        yield 'capital-p-dangit comment is a safe fix' => [
            'wordpress/capital-p-dangit',
            '',
            '// Built for Wordpress and word press.',
            '// Built for WordPress and WordPress.',
        ];

        yield 'capital-p-dangit text is not fixed without --potentially-unsafe' => [
            'wordpress/capital-p-dangit',
            '',
            "echo 'Powered by Wordpress';",
            "echo 'Powered by Wordpress';",
        ];

        yield 'capital-p-dangit text is fixed with --potentially-unsafe, URLs left alone' => [
            'wordpress/capital-p-dangit',
            '--potentially-unsafe',
            "echo 'Wordpress at https://wordpress.org/wordpress';",
            "echo 'WordPress at https://wordpress.org/wordpress';",
        ];

        yield 'wp-date-time UTC timestamp becomes time()' => [
            'wordpress/wp-date-time',
            '',
            "\$a = current_time( 'timestamp', true );\n\$b = \\current_time( 'U', 1 );",
            "\$a = time();\n\$b = \\time();",
        ];

        yield 'wp-date-time with a comment in the call is not fixed' => [
            'wordpress/wp-date-time',
            '',
            "\$a = current_time( 'timestamp', /* utc */ true );",
            "\$a = current_time( 'timestamp', /* utc */ true );",
        ];

        yield 'wp-i18n superfluous default domain is removed' => [
            'wordpress/wp-i18n',
            '',
            "__( 'a', 'default' );\n__( 'b', 'default', );\n_n_noop( domain: 'default', singular: 'c', plural: 'd' );\n__( 'e', 'default' /*x*/ );",
            "__( 'a' );\n__( 'b', );\n_n_noop( singular: 'c', plural: 'd' );\n__( 'e' /*x*/ );",
            ['default'],
        ];

        yield 'wp-i18n unordered placeholders are numbered with --potentially-unsafe' => [
            'wordpress/wp-i18n',
            '--potentially-unsafe',
            "__( '%s of %d', 'default' );\n__( \"%s of %s\", 'default' );",
            "__( '%1\$s of %2\$d' );\n__( \"%1\\\$s of %2\\\$s\" );",
            ['default'],
        ];

        yield 'dirname of __FILE__ becomes __DIR__, levels lowered by one' => [
            'generic/dirname',
            '',
            "\$a = \\dirname( __FILE__ );\n\$b = dirname(__FILE__, 1);\n\$c = dirname( __FILE__, 3 );\n\$d = dirname(__FILE__ /* x */);\n\$e = dirname(__FILE__, \$n);",
            "\$a = __DIR__;\n\$b = __DIR__;\n\$c = dirname( __DIR__, 2 );\n\$d = dirname(__FILE__ /* x */);\n\$e = dirname(__FILE__, \$n);",
        ];

        yield 'no-leading-backslash drops the backslash of an import' => [
            'generic/no-leading-backslash',
            '',
            "use \\Foo\\Bar;\nuse function \\foo, \\Bar\\baz as q;\nuse/*x*/\\Baz;",
            "use Foo\\Bar;\nuse function foo, Bar\\baz as q;\nuse/*x*/ Baz;",
        ];

        yield 'uppercase-magic-constants uppercases the constant' => [
            'generic/uppercase-magic-constants',
            '',
            'echo __line__, __Dir__, __FILE__;',
            'echo __LINE__, __DIR__, __FILE__;',
        ];

        yield 'lowercase-class-resolution-keyword lowercases the keyword' => [
            'generic/lowercase-class-resolution-keyword',
            '',
            "echo Foo::CLASS, \$object::Class, Foo::class, Foo::CONSTANT;",
            "echo Foo::class, \$object::class, Foo::class, Foo::CONSTANT;",
        ];

        yield 'lowercase-php-tag lowercases a later open tag' => [
            'generic/lowercase-php-tag',
            '',
            "echo 1;\n?>\n<?PHP echo 2; ?>",
            "echo 1;\n?>\n<?php echo 2; ?>",
        ];

        yield 'class-modifier-keyword-order puts readonly after final and abstract' => [
            'generic/class-modifier-keyword-order',
            '',
            "readonly final class A {}\nreadonly   abstract class B {}\nfinal readonly class C {}",
            "final readonly class A {}\nabstract readonly class B {}\nfinal readonly class C {}",
        ];

        yield 'constant-modifier-keyword-order puts final before the visibility' => [
            'generic/constant-modifier-keyword-order',
            '',
            "class A {\n\tpublic final const ONE = 1;\n\tfinal private const TWO = 2;\n}",
            "class A {\n\tfinal public const ONE = 1;\n\tfinal private const TWO = 2;\n}",
        ];

        yield 'short-form-type-keywords shortens the casts' => [
            'generic/short-form-type-keywords',
            '',
            "\$a = (boolean) \$x;\n\$b = ( Integer ) \$x;\n\$c = (bool) \$x;",
            "\$a = (bool) \$x;\n\$b = ( int ) \$x;\n\$c = (bool) \$x;",
        ];

        yield 'disallow-attribute-parentheses drops empty parentheses' => [
            'generic/disallow-attribute-parentheses',
            '',
            "#[Attr(), Other(1), Third( )]\nfunction a() {}\n#[Commented( /* c */ )]\nfunction b() {}",
            "#[Attr, Other(1), Third]\nfunction a() {}\n#[Commented( /* c */ )]\nfunction b() {}",
        ];

        yield 'no-useless-aliases drops an alias that repeats the name' => [
            'generic/no-useless-aliases',
            '',
            "use Foo\\Bar as Bar;\nuse function Foo\\strlen as STRLEN;\nuse const Foo\\X as x;\nuse Foo\\{A as A, B as C};\nuse Foo\\Baz /* c */ as Baz;",
            "use Foo\\Bar;\nuse function Foo\\strlen;\nuse const Foo\\X as x;\nuse Foo\\{A, B as C};\nuse Foo\\Baz /* c */ as Baz;",
        ];

        yield 'lowercase-use-function-const lowercases the keyword' => [
            'generic/lowercase-use-function-const',
            '',
            "use FUNCTION Foo\\helper;\nuse Const Foo\\LIMIT;\nuse Foo\\{A, Function b, CONST C};",
            "use function Foo\\helper;\nuse const Foo\\LIMIT;\nuse Foo\\{A, function b, const C};",
        ];

        yield 'including-file drops the parentheses around the path' => [
            'generic/including-file',
            '',
            "require_once('a.php');\nrequire ( 'b.php' ) . '';",
            "require_once 'a.php';\nrequire  'b.php'  . '';",
        ];

        yield 'including-file-warning makes an unconditional include a require' => [
            'generic/including-file-warning',
            '',
            "include 'a.php';\ninclude_once 'b.php';\nif (\$x) {\n\tinclude 'c.php';\n}",
            "require 'a.php';\nrequire_once 'b.php';\nif (\$x) {\n\tinclude 'c.php';\n}",
        ];

        yield 'closing-tag removes the tag at the end of a PHP-only file' => [
            'generic/closing-tag',
            '',
            "echo 'bye';\n\n?>",
            "echo 'bye';\n\n",
        ];

        yield 'closing-tag ends the statement before a trailing comment' => [
            'generic/closing-tag',
            '',
            'echo $foo //hello ?>',
            'echo $foo; //hello ',
        ];

        yield 'parentheses-spacing adds the WordPress spaces' => [
            'wordpress/parentheses-spacing',
            '',
            "if (! foo(\$a, array(1), [\$b])) {\n\t\$c = \$d[\$i] . \$d['k'] . \$d[ 0 ];\n}\ndo {\n} while (\$x);",
            "if ( ! foo( \$a, array( 1 ), [ \$b ] ) ) {\n\t\$c = \$d[ \$i ] . \$d['k'] . \$d[0];\n}\ndo {\n} while ( \$x );",
        ];

        yield 'parentheses-spacing: signed integer keys, tabs and parentheses in comments' => [
            'wordpress/parentheses-spacing',
            '',
            "\$a = \$d[ -1 ] . \$d[ +2 ] . \$d[-\$i];\nif (\t\$x\t) {\n}\nif /*(*/ (\$y) {\n}",
            "\$a = \$d[-1] . \$d[+2] . \$d[ -\$i ];\nif ( \$x ) {\n}\nif /*(*/ ( \$y ) {\n}",
        ];

        yield 'parentheses-spacing leaves strings, empty pairs and broken lines alone' => [
            'wordpress/parentheses-spacing',
            '',
            "\$s = \"\$a[0] {\$b[\$i]}\" . bar() . baz(\n\t1\n);",
            "\$s = \"\$a[0] {\$b[\$i]}\" . bar() . baz(\n\t1\n);",
        ];

        yield 'parentheses-spacing adds the space before an alternative-syntax colon' => [
            'wordpress/parentheses-spacing',
            '',
            "?>\n<?php if ( \$x ): ?>\n<?php elseif ( \$y ): ?>\n<?php else: ?>\n<?php endif; ?>\n<?php",
            "?>\n<?php if ( \$x ) : ?>\n<?php elseif ( \$y ) : ?>\n<?php else : ?>\n<?php endif; ?>\n<?php",
        ];

        yield 'array-alignment aligns arrows up to column 60 and leaves longer keys at one space' => [
            'wordpress/array-alignment',
            '',
            "\$a = array(\n\t\"one\" => 1,\n\t\"three_long_key\" => 2,\n\t\"x\"  =>   3,\n\t\"a_really_really_really_really_really_really_long_key_here_x\" => 4,\n\t\"yy\" =>\n\t\t5,\n\t\"nested\" => array(\n\t\t\"k\" => 1,\n\t\t\"longer_k\"    => 2,\n\t),\n\t7,\n);\n\$b = array( \"a\"   => 1, \"bb\" => 2 );\n[ \"q\" => \$x,  \"rr\" => \$y ] = \$z;\n",
            "\$a = array(\n\t\"one\"            => 1,\n\t\"three_long_key\" => 2,\n\t\"x\"              =>   3,\n\t\"a_really_really_really_really_really_really_long_key_here_x\" => 4,\n\t\"yy\"             =>\n\t\t5,\n\t\"nested\"         => array(\n\t\t\"k\"        => 1,\n\t\t\"longer_k\" => 2,\n\t),\n\t7,\n);\n\$b = array( \"a\" => 1, \"bb\" => 2 );\n[ \"q\" => \$x,  \"rr\" => \$y ] = \$z;\n",
        ];

        yield 'function-comment aligns parameter types, continuation lines and drops blank lines like phpcbf' => [
            'generic/function-comment',
            '',
            "/**\n * Doc.\n *\n * @param int \$a First one\n *               continues here.\n * @param string  \$bb Second.\n */\n\n\nfunction f( \$a, \$bb ) {\n}",
            "/**\n * Doc.\n *\n * @param int    \$a First one\n *                  continues here.\n * @param string \$bb Second.\n */\nfunction f( \$a, \$bb ) {\n}",
        ];

        yield 'inline-comment fixes spacing, # comments, blank comments and the blank line before a function end like phpcbf' =>
            [
                'generic/inline-comment',
                '',
                "function a() {\n\t//\n\t\$x = 1;\n\t#hash comment\n\t\$y = 2; // trailing\n\t//no space\n\t//   many spaces\n\t//\tTab\n\t// Fine.\n\t//\n\t// Second block ends without stop\n\n\t// Last comment.\n\n}\n\n\$f = function () {\n\t\$a = 1;\n\t// Closing comment\n\n\n};\n\nif ( \$a ) {\n} // end if\n\n// lonely\n\n// Another.",
                "function a() {\n\t\t\$x = 1;\n\t// hash comment\n\t\$y = 2; // trailing\n\t// no space\n\t// many spaces\n\t// Tab\n\t// Fine.\n\t//\n\t// Second block ends without stop\n\n\t// Last comment.\n}\n\n\$f = function () {\n\t\$a = 1;\n\t// Closing comment\n};\n\nif ( \$a ) {\n} // end if\n\n// lonely\n\n// Another.",
            ];

        yield 'function-call-signature moves a hugged array argument to its own line' => [
            'wordpress/function-call-signature',
            '',
            "function f() {\n\t\$formatter->append_start_tag( 'label', array(\n\t\t'one' => sprintf( '%s-subject', \$id ),\n\t\t'two' => array(\n\t\t\t'x' => 1,\n\t\t),\n\t) );\n}",
            "function f() {\n\t\$formatter->append_start_tag(\n\t\t'label',\n\t\tarray(\n\t\t\t'one' => sprintf( '%s-subject', \$id ),\n\t\t\t'two' => array(\n\t\t\t\t'x' => 1,\n\t\t\t),\n\t\t)\n\t);\n}",
        ];

        yield 'function-call-signature moves a hugged closure and re-indents its body' => [
            'wordpress/function-call-signature',
            '',
            "add_action( 'init', function () use ( \$a ) {\n\tif ( \$a ) {\n\t\treturn 1;\n\t}\n} );",
            "add_action(\n\t'init',\n\tfunction () use ( \$a ) {\n\t\tif ( \$a ) {\n\t\t\treturn 1;\n\t\t}\n\t}\n);",
        ];

        yield 'function-call-signature gives each of two closures a line of its own' => [
            'wordpress/function-call-signature',
            '',
            "\$queue->wait( function (): void {\n\techo 1;\n}, function ( \$reason ) use ( \$key ): void {\n\techo 2;\n} );",
            "\$queue->wait(\n\tfunction (): void {\n\t\techo 1;\n\t},\n\tfunction ( \$reason ) use ( \$key ): void {\n\t\techo 2;\n\t}\n);",
        ];

        yield 'function-call-signature puts a closing parenthesis and the second argument on their own lines' => [
            'wordpress/function-call-signature',
            '',
            "foo(\n\t'a',\n\t'b' );\nfoo( 'a',\n\t'b',\n\t'c'\n);",
            "foo(\n\t'a',\n\t'b'\n);\nfoo(\n\t'a',\n\t'b',\n\t'c'\n);",
        ];

        yield 'function-call-signature keeps a continuation line of the argument where it is' => [
            'wordpress/function-call-signature',
            '',
            "\$x = bar( 'text'\n\t. 'more', 3 );",
            "\$x = bar(\n\t'text'\n\t. 'more',\n\t3\n);",
        ];

        yield 'function-call-signature leaves heredocs, comments and single-line calls alone' => [
            'wordpress/function-call-signature',
            '',
            "foo( 'a', <<<EOT\n  keep\n  EOT\n);\nbar( 1, /* c */ 2,\n\t3 );\nbaz( 1, 2 );",
            "foo(\n\t'a',\n\t<<<EOT\n  keep\n  EOT\n);\nbar( 1, /* c */ 2,\n\t3 );\nbaz( 1, 2 );",
        ];

        yield 'function-call-signature keeps the lines of an interpolated multi-line string as they are' => [
            'wordpress/function-call-signature',
            '',
            "foo( 'a', function () {\n\treturn \"x\n{\$value}\n\$other\";\n} );",
            "foo(\n\t'a',\n\tfunction () {\n\t\treturn \"x\n{\$value}\n\$other\";\n\t}\n);",
        ];

        yield 'function-call-signature keeps a string nested in an interpolation inside a multi-line string' => [
            'wordpress/function-call-signature',
            '',
            "foo( 'a', function () {\n\treturn \"a\n{\$o->m('x\ny')}\nb\";\n} );",
            "foo(\n\t'a',\n\tfunction () {\n\t\treturn \"a\n{\$o->m('x\ny')}\nb\";\n\t}\n);",
        ];

        yield 'function-call-signature keeps an interpolated string nested in another interpolated string' => [
            'wordpress/function-call-signature',
            '',
            "foo( 'a', function () {\n\treturn \"a\n{\$o->m(\"x\n\$y\nz\")}\nb\";\n} );",
            "foo(\n\t'a',\n\tfunction () {\n\t\treturn \"a\n{\$o->m(\"x\n\$y\nz\")}\nb\";\n\t}\n);",
        ];

        yield 'function-call-signature keeps a string with a literal backtick between interpolations' => [
            'wordpress/function-call-signature',
            '',
            "foo( 'a', function () {\n\treturn \"a\n\$x`\$y\nb\";\n} );",
            "foo(\n\t'a',\n\tfunction () {\n\t\treturn \"a\n\$x`\$y\nb\";\n\t}\n);",
        ];

        yield 'property-declaration moves modifiers and fixes the space after the type' => [
            'generic/property-declaration',
            '',
            "class A {\n\tstatic public int   \$a;\n\treadonly protected ?string\$b;\n}",
            "class A {\n\tpublic static int \$a;\n\tprotected readonly ?string \$b;\n}",
        ];

        yield 'self-member-reference uses self:: without spaces' => [
            'generic/self-member-reference',
            '',
            "class A {\n\tconst B = 1;\n\tfunction f() {\n\t\treturn A::B + self :: B + SELF::B;\n\t}\n}",
            "class A {\n\tconst B = 1;\n\tfunction f() {\n\t\treturn self::B + self::B + self::B;\n\t}\n}",
        ];

        yield 'method-declaration moves final before and static after the visibility' => [
            'generic/method-declaration',
            '',
            "class A {\n\tstatic public function a() {}\n\tpublic final function b() {}\n}",
            "class A {\n\tpublic static function a() {}\n\tfinal public function b() {}\n}",
        ];

        yield 'else-if-declaration joins else if into elseif' => [
            'generic/else-if-declaration',
            '',
            "if (\$a) {\n} else  if (\$b) {\n}",
            "if (\$a) {\n} elseif (\$b) {\n}",
        ];

        yield 'static-in-final-class uses self' => [
            'generic/static-in-final-class',
            '',
            "final class A {\n\tconst B = 1;\n\tpublic function f(): static {\n\t\treturn static::B ? new static() : \$this;\n\t}\n}",
            "final class A {\n\tconst B = 1;\n\tpublic function f(): self {\n\t\treturn self::B ? new self() : \$this;\n\t}\n}",
        ];

        yield 'inline-control-structure adds braces, keeping a trailing comment inside' => [
            'generic/inline-control-structure',
            '',
            "if (\$a) foo(); else bar(); // done\nforeach (\$b as \$c)\n\tbaz(\$c);\nif (\$d);",
            "if (\$a) { foo(); } else { bar(); // done\n}\nforeach (\$b as \$c) {\n\tbaz(\$c);\n}\nif (\$d) {}",
        ];

        // The brace goes after the condition's own `)`, not after one inside a comment (phpcbf's output).
        yield 'inline-control-structure ignores a ) inside a comment before the body' => [
            'generic/inline-control-structure',
            '',
            "if ( \$x ) // legacy (old path)\n\tfoo();\nwhile (\$y) /* (wait) */ bar();",
            "if ( \$x ) { // legacy (old path)\n\tfoo();\n}\nwhile (\$y) { /* (wait) */ bar();\n}",
        ];

        yield 'inline-control-structure fixes the inner of nested bodies first, like phpcbf; a rerun fixes the outer' =>
            [
                'generic/inline-control-structure',
                '',
                "\tif (\$a)\n\t\tif (\$b) foo();",
                "\tif (\$a)\n\t\tif (\$b) { foo();\n\t\t}",
            ];

        yield 'disallow-standalone-post-increment-decrement moves the operator to the front' => [
            'generic/disallow-standalone-post-increment-decrement',
            '',
            "\$i++;\n\$obj->a[\$k] --;\n\$b = \$i++;",
            "++\$i;\n--\$obj->a[\$k];\n\$b = \$i++;",
        ];

        yield 'disallow-lonely-if merges the else and the if into elseif' => [
            'generic/disallow-lonely-if',
            '',
            "if (\$a) {\n\tx();\n} else {\n\tif (\$b) {\n\t\ty();\n\t} else {\n\t\tz();\n\t}\n}",
            "if (\$a) {\n\tx();\n} elseif (\$b) {\n\t\ty();\n\t} else {\n\t\tz();\n}",
        ];

        yield 'disallow-lonely-if leaves a comment around the inner if unfixed' => [
            'generic/disallow-lonely-if',
            '',
            "if (\$a) {\n\tx();\n} else { // why\n\tif (\$b) {\n\t\ty();\n\t}\n}",
            "if (\$a) {\n\tx();\n} else { // why\n\tif (\$b) {\n\t\ty();\n\t}\n}",
        ];

        yield 'control-signature puts one space after the brace unless a comment is in the way' => [
            'generic/control-signature',
            '',
            "if (\$a) {\n}\nelse {\n}\ntry {\n}catch (E \$e) {\n} // x\nfinally {\n}",
            "if (\$a) {\n} else {\n}\ntry {\n} catch (E \$e) {\n} // x\nfinally {\n}",
        ];

        yield 'double-quote-usage single-quotes strings that need no double quotes' => [
            'generic/double-quote-usage',
            '',
            "\$a = \"plain \\\"q\\\" \\\$x\";\n\$b = \"it's\";\n\$c = \"tab\\t\";",
            "\$a = 'plain \"q\" \$x';\n\$b = \"it's\";\n\$c = \"tab\\t\";",
        ];

        yield 'no-echo-sprintf calls printf directly' => [
            'generic/no-echo-sprintf',
            '',
            "echo sprintf( '%s', \$a );\necho \\VSPRINTF( '%s', [ \$a ] );",
            "printf( '%s', \$a );\n\\vprintf( '%s', [ \$a ] );",
        ];

        yield 'unnecessary-heredoc turns the heredoc into a nowdoc' => [
            'generic/unnecessary-heredoc',
            '',
            "\$a = <<<\"EOT\"\ncosts \\\$5 \\\\ more\nEOT;",
            "\$a = <<<'EOT'\ncosts \$5 \\ more\nEOT;",
        ];

        yield 'no-double-negative casts a double negative and trims a triple one' => [
            'generic/no-double-negative',
            '',
            "\$a = !!\$b;\n\$c = ! ! ! \$d;\n\$e = !!\$f instanceof Foo;",
            "\$a = (bool)\$b;\n\$c = ! \$d;\n\$e = !!\$f instanceof Foo;",
        ];

        // Regressions from review: a fix must never change behaviour or break the file, even where WPCS's does.
        yield 'capital-p-dangit leaves interpolated expressions alone' => [
            'wordpress/capital-p-dangit',
            '--potentially-unsafe',
            "echo \"{\$o->Wordpress} \$o->Wordpress Wordpress\";\necho <<<EOT\n{\$o->Wordpress} Wordpress\nEOT;",
            "echo \"{\$o->Wordpress} \$o->Wordpress WordPress\";\necho <<<EOT\n{\$o->Wordpress} WordPress\nEOT;",
        ];

        yield 'wp-date-time qualifies time() in a namespace' => [
            'wordpress/wp-date-time',
            '',
            "namespace App;\n\$a = current_time( 'timestamp', true );",
            "namespace App;\n\$a = \\time();",
        ];

        yield 'wp-date-time qualifies time() when a function named time is imported' => [
            'wordpress/wp-date-time',
            '',
            "use function Vendor\\time;\n\$a = current_time( 'U', true );",
            "use function Vendor\\time;\n\$a = \\time();",
        ];

        yield 'wp-i18n default domain removal skips a comma inside a comment' => [
            'wordpress/wp-i18n',
            '',
            "__( 'a' /* , keep */, 'default' );\n_n_noop( domain: 'default' /* , x */, singular: 'b', plural: 'c' );",
            "__( 'a' /* , keep */ );\n_n_noop( domain: 'default' /* , x */, singular: 'b', plural: 'c' );",
            ['default'],
        ];

        yield 'wp-i18n placeholder numbering skips a literal %%' => [
            'wordpress/wp-i18n',
            '--potentially-unsafe',
            "__( 'Literal %%s; %s of %s', 'default' );",
            "__( 'Literal %%s; %1\$s of %2\$s' );",
            ['default'],
        ];
    }

    /**
     * @param list<string> $textDomains
     */
    #[DataProvider('cases')]
    public function testFixMatchesPhpcbf(
        string $rule,
        string $flags,
        string $code,
        string $expected,
        array $textDomains = [],
    ): void {
        $this->lint($rule, ['text-domains' => $textDomains], $code, "--fix {$flags}");

        self::assertSame("<?php\n\n{$expected}\n", file_get_contents("{$this->directory}/fixture.php"));
    }
}
