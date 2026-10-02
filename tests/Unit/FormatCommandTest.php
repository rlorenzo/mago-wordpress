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
use function is_dir;
use function is_link;
use function json_encode;
use function mkdir;
use function rmdir;
use function symlink;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * `mago-wordpress format` through the real binary and preset: `--check` changes nothing and
 * shows a diff, `format` writes WordPress formatting, and a check after it passes.
 */
final class FormatCommandTest extends TestCase
{
    use TempProject {
        tearDown as private removeTempProject;
    }

    protected function tearDown(): void
    {
        // The test links the package under vendor/: remove that tree before the trait removes the rest.
        foreach (['vendor/rlorenzo/mago-wordpress', 'vendor/rlorenzo', 'vendor'] as $path) {
            $path = "{$this->directory}/{$path}";
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }

        $this->removeTempProject();
    }

    public function testFormatThenCheck(): void
    {
        $root = dirname(__DIR__, levels: 2);
        file_put_contents("{$this->directory}/composer.json", data: '{}');
        // The preset starts the worker through the relative path vendor/rlorenzo/mago-wordpress/...;
        // Mago 1.47.1 appends a command set here to the preset's instead of replacing it, so the
        // test provides that path rather than overriding the command.
        mkdir("{$this->directory}/vendor/rlorenzo", recursive: true);
        symlink($root, "{$this->directory}/vendor/rlorenzo/mago-wordpress");
        file_put_contents(
            "{$this->directory}/mago.toml",
            'extends = '
            . json_encode("{$root}/wordpress.mago.toml", flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "\nphp-version = \"8.1\"\n[source]\npaths = [\".\"]\nexcludes = [\"vendor/**\"]\n",
        );
        $unformatted = "<?php\nif (\$x): ?>\n\t<p><?php echo foo(\$a, [1, 2]); ?></p>\n<?php else: ?>\n\t<?php exit(); ?>\n<?php endif; ?>\n";
        file_put_contents("{$this->directory}/template.php", $unformatted);

        [$status, $output] = $this->format('--check');
        self::assertSame(1, $status, $output);
        self::assertStringContainsString('+++ b/template.php', $output);
        self::assertSame($unformatted, file_get_contents("{$this->directory}/template.php"));

        // `./template.php` and the absolute path name the same file as `template.php`.
        foreach (['./template.php', "{$this->directory}/template.php", './/'] as $path) {
            [$status, $output] = $this->format("--check {$path}");
            self::assertSame(1, $status, "{$path}: {$output}");
            self::assertStringContainsString('+++ b/template.php', $output, $path);
        }

        symlink('/etc', "{$this->directory}/outside_symlink");
        [$status, $output] = $this->format('--check outside_symlink');
        unlink("{$this->directory}/outside_symlink");
        self::assertSame(2, $status, $output);
        self::assertStringContainsString('is outside the project', $output);

        foreach (['/etc', '../outside.php', 'src/../../outside.php'] as $outside) {
            [$status, $output] = $this->format("--check {$outside}");
            self::assertSame(2, $status, "{$outside}: {$output}");
            self::assertStringContainsString('is outside the project', $output, $outside);
        }

        foreach (['vendor', './vendor/x'] as $vendor) {
            [$status, $output] = $this->format("--check {$vendor}");
            self::assertSame(1, $status, "{$vendor}: {$output}");
            self::assertStringContainsString('does not format vendor/', $output, $vendor);
        }

        [$status, $output] = $this->format();
        self::assertSame(0, $status, $output);
        self::assertSame(
            "<?php\nif ( \$x ) : ?>\n\t<p><?php echo foo( \$a, array( 1, 2 ) ); ?></p>\n<?php else : ?>\n\t<?php exit; ?>\n<?php endif; ?>\n",
            file_get_contents("{$this->directory}/template.php"),
        );

        [$status, $output] = $this->format('--check');
        self::assertSame(0, $status, $output);
    }

    public function testUnknownFlagIsAnError(): void
    {
        [$status, $output] = $this->format('--nope');
        self::assertSame(2, $status);
        self::assertStringContainsString('Usage: mago-wordpress format', $output);
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
