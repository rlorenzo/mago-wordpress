<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\PhpcsTokens;
use Rlorenzo\MagoWordPress\Settings;

use function array_fill_keys;
use function count;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strtoupper;

/**
 * Ports `WordPress.DB.DirectDatabaseQuery`.
 *
 * Like the sniff, a token walk (`PhpcsTokens`): every `$wpdb->` query method call is a
 * `DirectQuery`; a string in its statement naming ALTER, CREATE or DROP is a `SchemaChange`
 * (a `TRUNCATE ` query is skipped); and a cachable call is `NoCaching` unless its enclosing
 * function also calls a cache delete function (for `query`, `update`, `replace`, `delete`)
 * or a cache get function and then a cache set function.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DirectDatabaseQueryRule implements Rule
{
    private const SNIFF = 'WordPress.DB.DirectDatabaseQuery';

    private const CACHABLE = ['delete', 'get_var', 'get_col', 'get_row', 'get_results', 'query', 'replace', 'update'];

    private const CACHE_GET = [
        'wp_cache_get',
        'wp_cache_get_multiple',
        'wp_cache_get_multiple_salted',
        'wp_cache_get_salted',
    ];

    private const CACHE_SET = [
        'wp_cache_add',
        'wp_cache_add_multiple',
        'wp_cache_set',
        'wp_cache_set_multiple',
        'wp_cache_set_multiple_salted',
        'wp_cache_set_salted',
    ];

    private const CACHE_DELETE = [
        'wp_cache_delete',
        'wp_cache_delete_multiple',
        'wp_cache_flush_group',
        'wp_cache_flush_runtime',
        'clean_attachment_cache',
        'clean_blog_cache',
        'clean_bookmark_cache',
        'clean_category_cache',
        'clean_comment_cache',
        'clean_network_cache',
        'clean_object_term_cache',
        'clean_page_cache',
        'clean_post_cache',
        'clean_term_cache',
        'clean_user_cache',
    ];

    private readonly FileGate $gate;

    /** @var array<string, true> */
    private readonly array $cacheGet;

    /** @var array<string, true> */
    private readonly array $cacheSet;

    /** @var array<string, true> */
    private readonly array $cacheDelete;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->gate = FileGate::forWords(['wpdb']);
        $this->cacheGet = array_fill_keys([
            ...self::CACHE_GET,
            ...$settings->customList('custom-cache-get-functions'),
        ], value: true);
        $this->cacheSet = array_fill_keys([
            ...self::CACHE_SET,
            ...$settings->customList('custom-cache-set-functions'),
        ], value: true);
        $this->cacheDelete = array_fill_keys([
            ...self::CACHE_DELETE,
            ...$settings->customList('custom-cache-delete-functions'),
        ], value: true);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/direct-database-query',
            name: 'Direct database query',
            description: 'Reports direct $wpdb query calls, the ones without object caching in the same function, and queries that change the database schema.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $tokens = PhpcsTokens::of($context->file);
        $count = count($tokens);
        for ($at = 0; $at < $count; $at++) {
            if ($tokens[$at]['t'] !== 'var' || $tokens[$at]['x'] !== '$wpdb') {
                continue;
            }

            // The sniff resumes after the statement it reported.
            $at = $this->inspect($context, $tokens, $at) ?? $at;
        }
    }

    /**
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private function inspect(LintContext $context, array $tokens, int $at): ?int
    {
        $method = strtolower($tokens[$at + 2]['x'] ?? '');
        if (
            !in_array($tokens[$at + 1]['x'] ?? '', ['->', '?->'], strict: true)
            || !in_array($method, [...self::CACHABLE, 'insert'], strict: true)
        ) {
            return null;
        }

        $end = $at + 1;
        while (($tokens[$end]['t'] ?? ';') !== ';') {
            $end++;
        }

        if (($tokens[$end] ?? null) === null) {
            return null;
        }

        for ($k = $at + 1; $k < $end; $k++) {
            if (!in_array($tokens[$k]['t'], ['text', 'string', 'html'], strict: true)) {
                continue;
            }

            foreach (PhpcsTokens::textLines($tokens[$k]) as [$line, $offset]) {
                // TextStrings::stripQuotes().
                if (str_starts_with(
                    strtoupper((string) preg_replace('`^([\'"])(.*)\1$`Ds', replacement: '$2', subject: $line)),
                    'TRUNCATE ',
                )) {
                    // Truncating needs a direct query, and caching it is irrelevant.
                    return null;
                }

                if (preg_match('#\b(?:ALTER|CREATE|DROP)\b#i', $line) === 1) {
                    $this->warn(
                        $context,
                        'Attempting a database schema change is discouraged.',
                        $line,
                        $offset,
                        'SchemaChange',
                    );
                }
            }
        }

        $this->warn(
            $context,
            'Use of a direct database call is discouraged.',
            '$wpdb',
            $tokens[$at]['p'],
            'DirectQuery',
        );
        if (in_array($method, self::CACHABLE, strict: true) && !$this->isCached($tokens, $at, $method)) {
            $this->warn(
                $context,
                'Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete().',
                '$wpdb',
                $tokens[$at]['p'],
                'NoCaching',
            );
        }

        return $end;
    }

    /**
     * Whether the innermost function or closure around the call clears the cache (for a
     * write), or reads and then writes it.
     *
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private function isCached(array $tokens, int $at, string $method): bool
    {
        [$start, $end] = self::functionBody($tokens, $at) ?? [0, 0];
        $read = false;
        for ($k = $start + 1; $k < $end; $k++) {
            if ($tokens[$k]['t'] !== 'name' || ($tokens[$k + 1]['t'] ?? '') !== '(') {
                continue;
            }

            $name = strtolower($tokens[$k]['x']);
            if ($this->cacheDelete[$name] ?? false) {
                if (in_array($method, ['query', 'update', 'replace', 'delete'], strict: true)) {
                    return true;
                }
            } elseif ($this->cacheGet[$name] ?? false) {
                $read = true;
            } elseif ($read && ($this->cacheSet[$name] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The braces of the innermost function or closure body around token $at (phpcs's
     * `Conditions::getLastCondition()`; an arrow function is not a condition there).
     *
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     * @return null|array{int, int}
     */
    private static function functionBody(array $tokens, int $at): ?array
    {
        for ($f = $at - 1; $f >= 0; $f--) {
            if ($tokens[$f]['t'] !== 'function') {
                continue;
            }

            // Past the parameter list and any `use (...)`, to the body or a `;`.
            $k = $f + 1;
            while (!in_array($tokens[$k]['t'] ?? ';', ['{', ';'], strict: true)) {
                $k = $tokens[$k]['t'] === '(' ? ($tokens[$k]['m'] ?? $k) + 1 : $k + 1;
            }

            $close = $tokens[$k]['m'] ?? null;
            if ($close !== null && $tokens[$k]['t'] === '{' && $k < $at && $at < $close) {
                return [$k, $close];
            }
        }

        return null;
    }

    private function warn(LintContext $context, string $message, string $text, int $offset, string $code): void
    {
        $this->report->issue(
            $context,
            Issue::new($message, new Span($offset, $offset + strlen($text))),
            [
                self::SNIFF . '.' . $code,
            ],
        );
    }
}
