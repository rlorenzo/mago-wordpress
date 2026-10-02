<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Settings;

use function in_array;
use function preg_match;
use function preg_replace;
use function strtolower;
use function substr;
use function trim;

/**
 * Ports `WordPress.PHP.NoSilencedErrors`: the error control operator `@`, except before a call
 * to a function on the sniff's list of PHP functions that warn in normal use (`is_file()`,
 * `fopen()`, `unserialize()`, ...) or on the project's `custom-allowed-functions-list`.
 * WordPress-Core uses the PHP list; WordPress-Extra (and so the full standard) turns it off.
 *
 * Like the sniff, only an unqualified or fully qualified name directly followed by `(` counts as
 * a function call: `@\file()` is allowed, `@Foo::file()` and `@ns\file()` are reported.
 */
final class NoSilencedErrorsRule implements Rule
{
    private const CODE = 'WordPress.PHP.NoSilencedErrors.Discouraged';

    /** WPCS's `$allowedFunctionsList`. */
    private const PHP_FUNCTIONS = [
        'chdir',
        'opendir',
        'scandir',
        'file_exists',
        'file_get_contents',
        'file',
        'fileatime',
        'filectime',
        'filegroup',
        'fileinode',
        'filemtime',
        'fileowner',
        'fileperms',
        'filesize',
        'filetype',
        'fopen',
        'is_dir',
        'is_executable',
        'is_file',
        'is_link',
        'is_readable',
        'is_writable',
        'is_writeable',
        'lstat',
        'mkdir',
        'move_uploaded_file',
        'readfile',
        'readlink',
        'rename',
        'rmdir',
        'stat',
        'unlink',
        'ftp_chdir',
        'ftp_login',
        'ftp_rename',
        'stream_select',
        'stream_set_chunk_size',
        'deflate_add',
        'deflate_init',
        'inflate_add',
        'inflate_init',
        'readgzfile',
        'libxml_disable_entity_loader',
        'imagecreatefromstring',
        'imagecreatefromwebp',
        'unserialize',
    ];

    /**
     * After `@`, phpcs skips whitespace, comments, `\` and `&`; the next token must be a name
     * (not followed by `\`) whose next non-empty token is `(`.
     */
    private const CALL = '~\G(?:\s+|/\*.*?\*/|(?://|#(?!\[))[^\n]*|\\\\|&)*([a-z_\x80-\xff][\w\x80-\xff]*)(?:\s+|/\*.*?\*/|(?://|#(?!\[))[^\n]*)*\(~is';

    /** @var list<string> */
    private readonly array $allowed;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->allowed = [
            ...($settings->standard === 'WordPress-Core' ? self::PHP_FUNCTIONS : []),
            ...$settings->customList('custom-allowed-functions-list'),
        ];
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/no-silenced-errors',
            name: 'No silenced errors',
            description: 'Reports the error control operator `@`, except (under WordPress-Core) before PHP functions that warn in normal use, such as is_file() and fopen().',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::UnaryPrefix],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $operator = $file->getChildren($context->node)[0] ?? null;
        if ($operator === null || trim($file->getText($operator)) !== '@') {
            return;
        }

        $match = [];
        if (
            preg_match(self::CALL, $file->contents, $match, offset: $operator->span->end) === 1
            && in_array(strtolower($match[1]), $this->allowed, strict: true)
        ) {
            return;
        }

        $found = (string) preg_replace(
            '/\s+/',
            replacement: ' ',
            subject: substr($file->getText($context->node), offset: 0, length: 40),
        );
        $this->report->issue(
            $context,
            Issue::new(
                'Silencing errors is strongly discouraged. Use proper error checking instead. Found: ' . $found . '...',
                $operator->span,
                'error control operator',
            )->withHelp('Check for the error condition instead of suppressing it.'),
            [self::CODE],
        );
    }
}
