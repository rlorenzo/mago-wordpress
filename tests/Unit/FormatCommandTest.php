<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * `mago-wordpress format` through the real binary and preset: `--check` changes nothing and
 * shows a diff, `format` writes WordPress formatting, and a check after it passes.
 */
final class FormatCommandTest extends TestCase
{
    use TempProject;

    public function testFormatThenCheck(): void
    {
        $this->project();
        $unformatted = "<?php\nif (\$x): ?>\n\t<p><?php echo foo(\$a, [1, 2]); ?></p>\n<?php else: ?>\n\t<?php exit(); ?>\n<?php endif; ?>\n";
        file_put_contents("{$this->directory}/template.php", $unformatted);

        [$status, $output] = $this->format('--check');
        self::assertSame(1, $status, $output);
        self::assertStringContainsString('+++ b/template.php', $output);
        self::assertSame($unformatted, file_get_contents("{$this->directory}/template.php"));

        [$status, $output] = $this->format();
        self::assertSame(0, $status, $output);
        self::assertSame(
            "<?php\nif ( \$x ) : ?>\n\t<p><?php echo foo( \$a, array( 1, 2 ) ); ?></p>\n<?php else : ?>\n\t<?php exit; ?>\n<?php endif; ?>\n",
            file_get_contents("{$this->directory}/template.php"),
        );

        [$status, $output] = $this->format('--check');
        self::assertSame(0, $status, $output);
    }

    /**
     * A file whose formatting would add a finding (here a translators comment no longer on the
     * line before its string) is left as it was, and `--check` accepts it.
     */
    public function testFileThatFormattingBreaksIsLeftAlone(): void
    {
        $this->project();
        $source = <<<'PHP'
            <?php
            echo '<a class="' . esc_attr( $c ) . '"' .
            	/* translators: %s: percent done. */
            	' data-label="' . esc_attr( __( 'Checking (%1$s%)', 'd' ) ) . '"';

            PHP;
        file_put_contents("{$this->directory}/broken.php", $source);

        [$status, $output] = $this->format();
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('broken.php: left unformatted: formatting adds wordpress/wp-i18n', $output);
        self::assertSame($source, file_get_contents("{$this->directory}/broken.php"));

        [$status, $output] = $this->format('--check');
        self::assertSame(0, $status, $output);
    }

    public function testUnknownFlagIsAnError(): void
    {
        [$status, $output] = $this->format('--nope');
        self::assertSame(2, $status);
        self::assertStringContainsString('Usage: mago-wordpress format', $output);
    }

    private function project(): void
    {
        $root = dirname(__DIR__, levels: 2);
        file_put_contents("{$this->directory}/composer.json", data: '{}');
        file_put_contents(
            "{$this->directory}/mago.toml",
            'extends = '
            . json_encode("{$root}/wordpress.mago.toml", flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "\nphp-version = \"8.1\"\n[source]\npaths = [\".\"]\n[extension-hosts.wordpress]\ncommand = [\"php\", "
            . json_encode("{$root}/resources/worker.php", flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "]\n",
        );
    }

    /**
     * @return array{int, string}
     */
    private function format(string $flags = ''): array
    {
        $root = dirname(__DIR__, levels: 2);
        $output = [];
        $status = 0;
        exec(
            'cd '
            . escapeshellarg($this->directory)
            . ' && MAGO='
            . escapeshellarg("{$root}/vendor/bin/mago")
            . ' php '
            . escapeshellarg("{$root}/bin/mago-wordpress")
            . " format {$flags} 2>&1",
            $output,
            $status,
        );

        return [$status, implode("\n", $output)];
    }
}
