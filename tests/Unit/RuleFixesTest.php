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
            "++\$i;\n--\$obj->a[\$k] ;\n\$b = \$i++;",
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
