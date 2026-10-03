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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Sanitization;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function array_fill_keys;
use function count;
use function preg_match;
use function strtolower;

/**
 * Ports `WordPress.Security.NonceVerification`: `$_POST` or `$_FILES` (`Missing`), or
 * `$_GET` or `$_REQUEST` (`Recommended`, a warning in WPCS), read without a nonce check
 * (`wp_verify_nonce()`, `check_admin_referer()`, `check_ajax_referer()` or one of
 * `custom-nonce-verification-functions`) earlier in the function, or file, scope. An
 * `isset()`, comparison, type test or plain sanitizing of the value may come before the
 * nonce check. WPCS works over tokens, so this port does too (`PhpcsTokens`), including
 * its per-scope search cache, which decides some results.
 *
 * Mago gives a rule one level, so `Recommended` is reported at the rule's level too.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class NonceVerificationRule implements Rule
{
    private const SNIFF = 'WordPress.Security.NonceVerification';

    /** Superglobal => whether a missing nonce check is an error (`Missing`) rather than a warning. */
    private const SUPERGLOBALS = ['$_POST' => true, '$_FILES' => true, '$_GET' => false, '$_REQUEST' => false];

    private readonly Sanitization $sanitization;

    /** @var array<string, true> */
    private readonly array $nonceFunctions;

    /** @var array<string, true> */
    private readonly array $unslashingFunctions;

    /** @var array<int, array{end: int, nonce: false|int}> scope start => how far it was searched, and the nonce check found */
    private array $cache = [];

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->sanitization = new Sanitization($settings);
        $this->unslashingFunctions = array_fill_keys(Lists::UNSLASHING_FUNCTIONS, value: true);
        $this->nonceFunctions = array_fill_keys([
            'wp_verify_nonce',
            'check_admin_referer',
            'check_ajax_referer',
            ...$settings->customList('custom-nonce-verification-functions'),
        ], value: true);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/nonce-verification',
            name: 'Nonce verification',
            description: 'Reports form data read from $_POST, $_FILES, $_GET or $_REQUEST without a nonce check first.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (preg_match('/\$_(?:POST|FILES|GET|REQUEST)\b/', $context->file->contents) !== 1) {
            return;
        }

        $tokens = PhpcsTokens::of($context->file);
        $this->cache = [];
        $count = count($tokens->tokens);
        for ($index = 0; $index < $count; $index++) {
            $code = $tokens->code($index);
            // What a list holds is always assigned to.
            if ($code === 'T_LIST' && $tokens->code($index + 1) === '(') {
                $index = $tokens->closer($index + 1) ?? $index;
                continue;
            }

            if (
                $code === 'T_OPEN_SHORT_ARRAY'
                && ($closer = $tokens->closer($index)) !== null
                && ($tokens->code($closer + 1) === '=' || $tokens->code($index - 1) === 'T_AS')
            ) {
                $index = $closer;
                continue;
            }

            $content = $tokens->content($index);
            if (
                $code !== 'T_VARIABLE'
                || (self::SUPERGLOBALS[$content] ?? null) === null
                || $tokens->isOOProperty($index)
            ) {
                continue;
            }

            $start = $tokens->functionOpener($index) ?? 0;
            $needs = $this->needsNonceCheck($tokens, $index, $start);
            if ($needs === null || $this->hasNonceCheck($tokens, $index, $start, $needs === 'after')) {
                continue;
            }

            [$from, $to] = $tokens->span($index);
            $this->report->issue(
                $context,
                Issue::new('Processing form data without nonce verification.', new Span($from, $to))->withHelp(
                    'Verify a nonce with wp_verify_nonce(), check_admin_referer() or check_ajax_referer() before reading the request.',
                ),
                [self::SNIFF . '.' . (self::SUPERGLOBALS[$content] ? 'Missing' : 'Recommended')],
            );
        }
    }

    /**
     * NULL when no nonce check is needed; `after` when it may come after the token.
     *
     * @return null|'before'|'after'
     */
    private function needsNonceCheck(PhpcsTokens $tokens, int $index, int $start): ?string
    {
        $nonceCheck = $tokens->inFunctionCall($index, $this->nonceFunctions);
        if ($nonceCheck !== null) {
            // This is the nonce check.
            $this->setCache($start, $index, $nonceCheck);

            return null;
        }

        if ($tokens->inUnset($index) || $tokens->isAssignment($index, false)) {
            return null;
        }

        $after =
            $tokens->inIssetOrEmpty($index)
            || $tokens->inTypeTest($index)
            || $tokens->isComparison($index)
            || $tokens->isAssignment($index)
            || $tokens->inArrayComparison($index)
            || $tokens->inFunctionCall($index, $this->unslashingFunctions) !== null
            || $this->sanitization->isOnlySanitized($tokens, $index);

        return $after ? 'after' : 'before';
    }

    /** @mago-expect lint:halstead */
    private function hasNonceCheck(PhpcsTokens $tokens, int $index, int $start, bool $allowAfter): bool
    {
        $end = $index;
        if ($allowAfter) {
            $end = $start === 0 ? count($tokens->tokens) : (int) $tokens->closer($start);
        }

        $cached = $this->cache[$start] ?? ['end' => 0, 'nonce' => false];
        if ($cached['nonce'] !== false) {
            return $allowAfter || $cached['nonce'] < $index;
        }

        if ($end <= $cached['end']) {
            return false;
        }

        $searchStart = $cached['end'] > $start ? $cached['end'] : $start;
        for ($at = $searchStart; $at < $end; $at++) {
            $closer = $tokens->scopeCloser($at);
            if ($closer !== null) {
                $at = $closer;
                continue;
            }

            if (
                $tokens->code($at) === 'T_STRING'
                && ($this->nonceFunctions[strtolower($tokens->content($at))] ?? null) !== null
                && !$tokens->hasObjectOperatorBefore($at)
                && !$tokens->isNamespaced($at)
            ) {
                $this->setCache($start, $end, $at);

                return true;
            }
        }

        $this->setCache($start, $end, false);

        return false;
    }

    private function setCache(int $start, int $end, false|int $nonce): void
    {
        if (($this->cache[$start] ?? null) === null) {
            $this->cache[$start] = ['end' => $end, 'nonce' => $nonce];

            return;
        }

        if ($end > $this->cache[$start]['end']) {
            $this->cache[$start]['end'] = $end;
        }

        $this->cache[$start]['nonce'] = $nonce;
    }
}
