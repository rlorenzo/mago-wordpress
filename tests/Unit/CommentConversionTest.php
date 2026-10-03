<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\CommentConversion;

use function array_column;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function symlink;

final class CommentConversionTest extends TestCase
{
    use TempProject;

    public function testIgnoreBecomesACountedPragma(): void
    {
        $source = <<<'PHP'
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped upstream.
            echo $a,
                $b;
            echo $c; // phpcs:ignore WordPress.Security.EscapeOutput, WordPress.PHP.DontExtract
            PHP;

        $result = CommentConversion::convert($source, [
            [3, 'wordpress/escape-output'],
            [4, 'wordpress/escape-output'],
            [5, 'wordpress/escape-output'],
            [5, 'wordpress/dont-extract'],
            [5, 'cyclomatic-complexity'],
        ]);

        self::assertSame(<<<'PHP'
            <?php
            // @mago-expect lint:wordpress/escape-output(2) -- Escaped upstream.
            echo $a,
                $b;
            echo $c; // @mago-expect lint:wordpress/escape-output, lint:wordpress/dont-extract
            PHP, $result['source']);
        self::assertSame(['wordpress/escape-output' => 3, 'wordpress/dont-extract' => 1], $result['claimed']);
    }

    public function testWarningCodeConvertsToTheCompanionRule(): void
    {
        $source = <<<'PHP'
            <?php
            $page = absint($_GET['paged'] ?? 1); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            PHP;

        $result = CommentConversion::convert($source, [[2, 'wordpress/nonce-verification-warning']]);

        self::assertSame(<<<'PHP'
            <?php
            $page = absint($_GET['paged'] ?? 1); // @mago-expect lint:wordpress/nonce-verification-warning
            PHP, $result['source']);
    }

    public function testCommentThatCoversNothingKeepsOnlyItsReason(): void
    {
        $source = <<<'PHP'
            <?php
            // phpcs:ignore WordPress.WhiteSpace.OperatorSpacing -- exact match required
            $a = 1;
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $b = 2;
            $c = 3; // phpcs:ignore WordPress.Security.EscapeOutput
            PHP;

        $result = CommentConversion::convert($source, [[5, 'wordpress/escape-output']]);

        self::assertSame("<?php\n// Exact match required.\n\$a = 1;\n\$b = 2;\n\$c = 3;", $result['source']);
        self::assertSame(['kept as plain comment', 'dropped', 'dropped'], array_column($result['notes'], 1));
    }

    public function testInlineHtmlGetsAThreeLinePhpBlock(): void
    {
        $source = "<?php ?>\n\t<?php // phpcs:ignore WordPress.WP.EnqueuedResources -- Dynamic URL ?>\n\t<script src=\"x.js\"></script>";

        $result = CommentConversion::convert($source, [[3, 'wordpress/enqueued-resources']]);

        self::assertSame(
            "<?php ?>\n\t<?php\n\t// @mago-expect lint:wordpress/enqueued-resources -- Dynamic URL\n\t?>\n\t<script src=\"x.js\"></script>",
            $result['source'],
        );
    }

    public function testRegionPlacesAPragmaBeforeEachStatement(): void
    {
        $source = <<<'PHP'
            <?php
            init();
            // phpcs:disable WordPress.DB.PreparedSQL -- Table names.
            $wpdb->query( "A $t" );

            $wpdb->query(
                "B $t"
            );
            // phpcs:enable WordPress.DB.PreparedSQL
            PHP;

        $result = CommentConversion::convert($source, [
            [4, 'wordpress/prepared-sql'],
            [7, 'wordpress/prepared-sql'],
        ]);

        self::assertSame(<<<'PHP'
            <?php
            init();
            // @mago-expect lint:wordpress/prepared-sql -- Table names.
            $wpdb->query( "A $t" );

            // @mago-expect lint:wordpress/prepared-sql -- Table names.
            $wpdb->query(
                "B $t"
            );
            PHP, $result['source']);
    }

    public function testRegionOverAFunctionMovesToItsDocblock(): void
    {
        $source = <<<'PHP'
            <?php
            /**
             * Reads the form.
             */
            function f() {
                // phpcs:disable WordPress.Security.NonceVerification.Missing
                $a = $_POST['a'];
                $b = $_POST['b'];
                // phpcs:enable WordPress.Security.NonceVerification.Missing
                return $a . $b;
            }
            PHP;

        $result = CommentConversion::convert($source, [
            [7, 'wordpress/nonce-verification'],
            [8, 'wordpress/nonce-verification'],
        ]);

        self::assertSame(<<<'PHP'
            <?php
            /**
             * Reads the form.
             * @mago-expect lint:wordpress/nonce-verification(2)
             */
            function f() {
                $a = $_POST['a'];
                $b = $_POST['b'];
                return $a . $b;
            }
            PHP, $result['source']);
    }

    public function testSupersededCoreCodesAreRetargetedAndRecounted(): void
    {
        $source = <<<'PHP'
            <?php
            /**
             * @mago-expect lint:nonce-verification(3) -- Verified by the caller.
             */
            function f() {
                return $_POST['a'] . $_POST['b'];
            }
            // @mago-expect lint:no-unescaped-output, lint:no-debug-symbols -- CLI.
            echo $x; var_dump( $x );
            // @mago-ignore lint:prepared-sql -- Static.
            $wpdb->query( 'SELECT 1' );
            PHP;

        $result = CommentConversion::convert($source, [
            [6, 'wordpress/nonce-verification'],
            [6, 'wordpress/nonce-verification'],
            [9, 'wordpress/escape-output'],
        ]);

        self::assertSame(<<<'PHP'
            <?php
            /**
             * @mago-expect lint:wordpress/nonce-verification(2) -- Verified by the caller.
             */
            function f() {
                return $_POST['a'] . $_POST['b'];
            }
            // @mago-expect lint:no-debug-symbols, lint:wordpress/escape-output -- CLI.
            echo $x; var_dump( $x );
            // Static.
            $wpdb->query( 'SELECT 1' );
            PHP, $result['source']);
    }

    public function testIgnoreFileIsListedNotConverted(): void
    {
        $result = CommentConversion::convert("<?php\n// phpcs:ignoreFile\necho \$a;", [[3, 'wordpress/escape-output']]);

        self::assertSame("<?php\n// phpcs:ignoreFile\necho \$a;", $result['source']);
        self::assertSame('not convertible', $result['notes'][0][1]);
    }

    /**
     * The whole command against a real worker: the lint run ignores phpcs comments, the
     * written pragmas leave nothing unfulfilled, and the pragma behaviours it relies on hold.
     */
    public function testCommandConvertsThroughARealLintRun(): void
    {
        $root = dirname(__DIR__, levels: 2);
        $this->lint('wordpress/escape-output', [], '');
        symlink("{$root}/vendor", "{$this->directory}/vendor");
        file_put_contents("{$this->directory}/fixture.php", <<<'PHP'
            <?php

            function f( $a ) {
                // phpcs:ignore WordPress.Security.EscapeOutput -- Trusted.
                echo $a, $a;
                echo $a; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            PHP);

        $output = [];
        $status = 0;
        exec(
            'cd '
            . escapeshellarg($this->directory)
            . ' && php '
            . escapeshellarg("{$root}/bin/mago-wordpress")
            . ' convert-comments --write fixture.php 2>&1',
            $output,
            $status,
        );

        self::assertSame(0, $status, implode("\n", $output));
        self::assertStringContainsString('converted: 2', implode("\n", $output));
        self::assertStringContainsString(
            "// @mago-expect lint:wordpress/escape-output(2) -- Trusted.\n    echo \$a, \$a;\n"
            . "    echo \$a; // @mago-expect lint:wordpress/escape-output\n",
            (string) file_get_contents("{$this->directory}/fixture.php"),
        );
    }

    public function testCommandOnDotConvertsAFileWithNoIssues(): void
    {
        $root = dirname(__DIR__, levels: 2);
        // Without `[source] paths`, `mago list-files` lists nothing; the file must come from it, as it
        // has no issues for the lint run to report.
        $this->lint('wordpress/escape-output', [], '', linterToml: "[source]\npaths = [\"fixture.php\"]\n");
        symlink("{$root}/vendor", "{$this->directory}/vendor");
        file_put_contents("{$this->directory}/fixture.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            // phpcs:ignore WordPress.Security.EscapeOutput
            $a = 1;

            PHP);

        $output = [];
        $status = 0;
        exec(
            'cd '
            . escapeshellarg($this->directory)
            . ' && php '
            . escapeshellarg("{$root}/bin/mago-wordpress")
            . ' convert-comments --write . 2>&1',
            $output,
            $status,
        );

        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame(
            "<?php\n\ndeclare(strict_types=1);\n\n\$a = 1;\n",
            (string) file_get_contents("{$this->directory}/fixture.php"),
        );
    }

    /**
     * Pragma behaviours the command must avoid relying on (Mago 1.51.0).
     */
    public function testPragmaPitfalls(): void
    {
        [, $output] = $this->lint('wordpress/escape-output,wordpress/enqueued-resources', [], <<<'PHP'
            function f( $a ) {
                // @mago-ignore lint:wordpress/escape-output
                echo $a, $a;
                // @mago-expect lint:wordpress/escape-output
                $b = 1;
            }
            ?>
            <?php // @mago-expect lint:wordpress/enqueued-resources ?>
            <script src="http://example.com/x.js"></script>
            PHP, '--reporting-format emacs');

        self::assertStringContainsString('fixture.php:5:', $output, '@mago-ignore without a count silences one issue');
        self::assertStringContainsString('fixture.php:6:8:warning - unfulfilled-expect', $output);
        self::assertStringContainsString('fixture.php:10:', $output, 'a one-line block pragma misses inline HTML');
    }
}
