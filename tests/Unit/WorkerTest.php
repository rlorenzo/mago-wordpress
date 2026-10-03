<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function is_link;
use function json_encode;
use function mkdir;
use function rmdir;
use function str_repeat;
use function symlink;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const PATH_SEPARATOR;

/**
 * The real worker under every rule of the full standard, as `mago lint` runs it.
 */
final class WorkerTest extends TestCase
{
    use TempProject {
        tearDown as private removeTempProject;
    }

    protected function tearDown(): void
    {
        // lintWith() links the package under vendor/ and adds an ini directory: remove them before
        // the trait removes the rest.
        foreach (['vendor/rlorenzo/mago-wordpress', 'vendor/rlorenzo', 'vendor', 'ini/memory.ini', 'ini'] as $path) {
            $path = "{$this->directory}/{$path}";
            if (is_link($path) || is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }

        $this->removeTempProject();
    }

    /**
     * A 380 KB file dense with findings ran the worker out of memory at PHP's default 128M
     * (two token lists of the whole file, held next to each other and to every issue).
     */
    public function testFindingDenseFileLintsAtTheDefaultMemoryLimit(): void
    {
        $function = <<<'PHP'
            function wpx_f($a){ global $wpdb; if ( $a == $_GET["k"] ) { echo $a . $wpdb->get_var( "SELECT * FROM t WHERE id = $a" ); } $r = in_array( $a, array(1,2) ); return mt_rand(); }

            PHP;
        // 2,000 copies of one function make ~380 KB.
        file_put_contents("{$this->directory}/big.php", "<?php\n" . str_repeat($function, times: 2000));

        [$status, $output] = $this->lintWith('{}', 'big.php');

        self::assertSame(1, $status, $output);
        self::assertStringContainsString('error[wordpress/escape-output]: 4000', $output);
        self::assertStringContainsString('error[wordpress/validated-sanitized-input]: 2000', $output);
    }

    public function testInvalidSettingStopsTheWorkerWithOneReadableLine(): void
    {
        file_put_contents("{$this->directory}/a.php", data: "<?php\necho 1;\n");

        [$status, $output] = $this->lintWith('{"levels": "warning"}', 'a.php');

        self::assertSame(2, $status, $output);
        self::assertStringContainsString(
            'mago-wordpress: invalid configuration: composer.json extra.mago-wordpress: `levels` must be an object',
            $output,
        );
    }

    /**
     * @return array{int, string} exit status and combined output
     */
    private function lintWith(string $settings, string $file): array
    {
        $root = dirname(__DIR__, levels: 2);
        file_put_contents("{$this->directory}/composer.json", '{"extra": {"mago-wordpress": ' . $settings . '}}');
        // The preset starts the worker through vendor/rlorenzo/mago-wordpress/, as in a real project;
        // overriding the command does not work on Mago 1.47.1, which appends it to the preset's.
        mkdir("{$this->directory}/vendor/rlorenzo", recursive: true);
        symlink($root, "{$this->directory}/vendor/rlorenzo/mago-wordpress");
        file_put_contents(
            "{$this->directory}/mago.toml",
            'extends = '
            . json_encode("{$root}/wordpress.mago.toml", flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "\nversion = \"1\"\nphp-version = \"8.1\"\n",
        );
        // PHP's default memory_limit, whatever the machine's php.ini says; the leading separator
        // keeps the default scan directory, so the worker's extensions still load.
        mkdir("{$this->directory}/ini");
        file_put_contents("{$this->directory}/ini/memory.ini", data: "memory_limit=128M\n");

        $output = [];
        $status = 0;
        exec(
            'PHP_INI_SCAN_DIR='
            . escapeshellarg(PATH_SEPARATOR . "{$this->directory}/ini")
            . ' '
            . escapeshellarg("{$root}/vendor/bin/mago")
            . ' --workspace '
            . escapeshellarg($this->directory)
            . ' lint --reporting-format code-count '
            . escapeshellarg($file)
            . ' 2>&1',
            $output,
            $status,
        );

        return [$status, implode("\n", $output)];
    }
}
