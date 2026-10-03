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
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Internal\WordPress\PhpcsTokens;

use function array_fill_keys;
use function count;
use function preg_match;
use function strlen;
use function strtolower;
use function trim;

/**
 * Ports `WordPress.DB.PreparedSQL`.
 *
 * The sniff is a token walk over the first argument of a `$wpdb` query method, so this
 * rule walks `PhpcsTokens`. Every token outside the sniff's safe set (string literals, numbers,
 * concatenation, arithmetic, casts, brackets, object operators) is reported, except a
 * variable after an `(int)`, `(float)` or `(bool)` cast, `$wpdb` itself, an escaping
 * function call, and a formatting function name.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:halstead
 * @mago-expect lint:kan-defect
 */
final class PreparedSqlRule implements Rule
{
    private const SNIFF = 'WordPress.DB.PreparedSQL';

    private const METHODS = ['get_var', 'get_col', 'get_row', 'get_results', 'prepare', 'query'];

    /** The sniff's `$SQLEscapingFunctions` and `$SQLAutoEscapedFunctions`. */
    private const ESCAPING = ['absint', 'esc_sql', 'floatval', 'intval', 'like_escape', 'count'];

    /** Token types the walk skips (the sniff's `$ignored_tokens`). */
    private const IGNORED = [
        'ignored' => true,
        'text' => true,
        'ns' => true,
        'objop' => true,
        'safecast' => true,
        '(' => true,
        ')' => true,
        '{' => true,
        '}' => true,
        ',' => true,
    ];

    private readonly FileGate $gate;

    /** @var array<string, true> */
    private readonly array $methods;

    /** @var array<string, true> */
    private readonly array $escaping;

    /** @var array<string, true> */
    private readonly array $formatting;

    public function __construct(
        private readonly Report $report,
    ) {
        $this->gate = FileGate::forWords(['wpdb']);
        $this->methods = array_fill_keys(self::METHODS, value: true);
        $this->escaping = array_fill_keys(self::ESCAPING, value: true);
        $this->formatting = array_fill_keys(Lists::FORMATTING_FUNCTIONS, value: true);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prepared-sql',
            name: 'Prepared SQL',
            description: 'Reports variables, function calls and interpolated variables in the query passed to $wpdb->query(), get_var(), get_col(), get_row(), get_results() and prepare() that are not escaped or passed through $wpdb->prepare() placeholders.',
            defaultLevel: Level::Error,
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
        $at = 0;
        while ($at < $count) {
            $i = $at;
            $end = $at;
            if ($this->isEntry($tokens, $at) && $this->wpdbCall($tokens, $at, $i, $end)) {
                $at = $this->walk($context, $tokens, $i, $end);
                continue;
            }

            $at++;
        }
    }

    /**
     * A `$wpdb` variable, or a `wpdb` name that is not namespaced (a static call).
     *
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private function isEntry(array $tokens, int $at): bool
    {
        $token = $tokens[$at];
        if ($token['t'] === 'var') {
            return $token['x'] === '$wpdb';
        }

        if ($token['t'] !== 'name' || strtolower($token['x']) !== 'wpdb') {
            return false;
        }

        return !(($tokens[$at - 1]['t'] ?? '') === 'ns' && ($tokens[$at - 2]['t'] ?? '') === 'name');
    }

    /**
     * WPCS's `is_wpdb_method_call()`: sets $i to the token after the method name, and on a
     * query method sets $end past its first argument.
     *
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private function wpdbCall(array $tokens, int $at, int &$i, int &$end): bool
    {
        $method = $tokens[$at + 2] ?? null;
        $open = $tokens[$at + 3] ?? null;
        if (($tokens[$at + 1]['t'] ?? '') !== 'objop' || $method === null || $open === null) {
            return false;
        }

        $i = $at + 3;
        if (
            $open['t'] !== '('
            || ($open['m'] ?? null) === null
            || !($this->methods[strtolower($method['x'])] ?? false)
        ) {
            return false;
        }

        $end = PhpcsTokens::endOfStatement($tokens, $at + 4);
        if ($tokens[$end]['t'] !== ',') {
            $end++;
        }

        return true;
    }

    /**
     * The sniff's walk over the query tokens; returns where the file scan resumes.
     *
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private function walk(LintContext $context, array $tokens, int $i, int $end): int
    {
        for (; $i < $end; $i++) {
            $token = $tokens[$i] ?? null;
            if ($token === null) {
                break;
            }

            $type = $token['t'];
            if ((self::IGNORED[$type] ?? false) || ($type === '[' || $type === ']') && !($token['short'] ?? false)) {
                continue;
            }

            if ($type === 'string') {
                foreach ($token['e'] ?? [] as [$embed, $offset]) {
                    if (preg_match('`^\{?\$\{?wpdb\??->`', $embed) !== 1) {
                        // phpcs names the string's line (its own token) that holds the embed.
                        $at = '';
                        foreach (PhpcsTokens::textLines($token) as [$line, $start]) {
                            $at = $start <= $offset ? trim($line) : $at;
                        }

                        $this->found(
                            $context,
                            "Use placeholders and \$wpdb->prepare(); found interpolated variable {$embed} at {$at}",
                            new Span($offset, $offset + strlen($embed)),
                            'InterpolatedNotPrepared',
                        );
                    }
                }

                continue;
            }

            if ($type === 'var') {
                if ($token['x'] === '$wpdb') {
                    $this->wpdbCall($tokens, $i, $i, $end);
                    continue;
                }

                if (($tokens[$i - 1]['t'] ?? '') === 'safecast') {
                    continue;
                }
            }

            if ($type === 'name') {
                $name = strtolower($token['x']);
                $next = $tokens[$i + 1] ?? null;
                if ($this->escaping[$name] ?? false) {
                    if ($next !== null && $next['t'] === '(' && ($next['m'] ?? null) !== null) {
                        $i = $next['m'];
                        continue;
                    }
                } elseif ($this->formatting[$name] ?? false) {
                    continue;
                }
            }

            $this->found(
                $context,
                "Use placeholders and \$wpdb->prepare(); found {$token['x']}",
                new Span($token['p'], $token['p'] + strlen($token['x'])),
                'NotPrepared',
            );
        }

        return $end;
    }

    private function found(LintContext $context, string $message, Span $span, string $code): void
    {
        $this->report->issue(
            $context,
            Issue::new($message, $span)->withHelp(
                'Pass the value through a $wpdb->prepare() placeholder, or escape it with esc_sql(), absint() or an (int) cast.',
            ),
            [self::SNIFF . '.' . $code],
        );
    }
}
