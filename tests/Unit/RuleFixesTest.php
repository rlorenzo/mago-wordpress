<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Applies a rule's fixes through a real worker with `mago lint --fix` and checks the result
 * against what phpcbf writes for the same code (WPCS's `.inc.fixed` files).
 */
final class RuleFixesTest extends TestCase
{
    private const FILES = ['composer.json', 'mago.toml', 'fixture.php'];

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('mago-wordpress-', more_entropy: true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (self::FILES as $name) {
            if (!is_file("{$this->directory}/{$name}")) {
                continue;
            }

            unlink("{$this->directory}/{$name}");
        }

        rmdir($this->directory);
    }

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
        $worker = dirname(__DIR__, levels: 2) . '/resources/worker.php';
        $settings = ['mago-wordpress' => ['text-domains' => $textDomains]];
        file_put_contents("{$this->directory}/composer.json", json_encode([
            'extra' => $settings,
        ], flags: JSON_THROW_ON_ERROR));
        file_put_contents(
            "{$this->directory}/mago.toml",
            "version = \"1\"\nphp-version = \"8.1\"\n[extension-hosts.wordpress]\ncommand = [\"php\", "
            . json_encode($worker, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "]\n",
        );
        file_put_contents("{$this->directory}/fixture.php", "<?php\n\n{$code}\n");

        $mago = dirname(__DIR__, levels: 2) . '/vendor/bin/mago';
        $output = [];
        exec(
            escapeshellarg($mago)
            . ' --workspace '
            . escapeshellarg($this->directory)
            . ' lint --only '
            . escapeshellarg($rule)
            . " --fix {$flags} fixture.php 2>&1",
            $output,
        );

        self::assertSame("<?php\n\n{$expected}\n", file_get_contents("{$this->directory}/fixture.php"));
    }
}
