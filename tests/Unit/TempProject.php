<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function glob;
use function implode;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const GLOB_BRACE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * A throwaway project directory per test, and a helper that lints a fixture in it through
 * a real worker whose settings come from composer.json, as a consuming project's would.
 */
trait TempProject
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('mago-wordpress-', more_entropy: true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = glob("{$this->directory}/{,.}*[!.]", GLOB_BRACE);
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    /**
     * @param array<string, mixed> $settings composer.json `extra.mago-wordpress`
     * @param string $linterToml extra `mago.toml` lines placed before the extension host
     * @return array{int, string} exit status and combined output
     */
    private function lint(
        string $rule,
        array $settings,
        string $code,
        string $flags = '',
        string $linterToml = '',
    ): array {
        $root = dirname(__DIR__, levels: 2);
        file_put_contents("{$this->directory}/composer.json", json_encode([
            'extra' => ['mago-wordpress' => $settings],
        ], flags: JSON_THROW_ON_ERROR));
        file_put_contents(
            "{$this->directory}/mago.toml",
            "version = \"1\"\nphp-version = \"8.1\"\n{$linterToml}[extension-hosts.wordpress]\ncommand = [\"php\", "
            . json_encode("{$root}/resources/worker.php", flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "]\n",
        );
        file_put_contents("{$this->directory}/fixture.php", "<?php\n\n{$code}\n");

        $output = [];
        $status = 0;
        exec(
            escapeshellarg("{$root}/vendor/bin/mago")
            . ' --workspace '
            . escapeshellarg($this->directory)
            . ' lint --only '
            . escapeshellarg($rule)
            . " {$flags} fixture.php 2>&1",
            $output,
            $status,
        );

        return [$status, implode("\n", $output)];
    }
}
