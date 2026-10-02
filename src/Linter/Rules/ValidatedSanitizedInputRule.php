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
use Rlorenzo\MagoWordPress\Settings;

use function array_diff_assoc;
use function array_map;
use function array_pop;
use function implode;
use function in_array;
use function preg_match;
use function strlen;
use function strtolower;

/**
 * Ports `WordPress.Security.ValidatedSanitizedInput`: a superglobal array element read
 * without a check that the key exists (`InputNotValidated`), without a sanitizing function
 * (`InputNotSanitized`), or without `wp_unslash()` before one (`MissingUnslash`); and a
 * superglobal interpolated into a string (`InputNotValidatedNotSanitized`). WPCS works over
 * tokens, so this port does too (`PhpcsTokens`). Its `check_validation_in_scope_only`
 * property has no setting: the validation is looked for anywhere earlier in the scope.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ValidatedSanitizedInputRule implements Rule
{
    private const SNIFF = 'WordPress.Security.ValidatedSanitizedInput';

    private const SUPERGLOBALS = [
        '$_SERVER',
        '$_GET',
        '$_POST',
        '$_FILES',
        '$_COOKIE',
        '$_SESSION',
        '$_REQUEST',
        '$_ENV',
    ];

    /** WordPress slashes these, so they need unslashing. */
    private const SLASHED = ['$_COOKIE', '$_GET', '$_POST', '$_REQUEST', '$_SERVER'];

    private readonly Sanitization $sanitization;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->sanitization = new Sanitization($settings);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/validated-sanitized-input',
            name: 'Validated sanitized input',
            description: 'Reports superglobal input that is not validated, unslashed and sanitized before use.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (preg_match('/_(?:SERVER|GET|POST|FILES|COOKIE|SESSION|REQUEST|ENV)\b/', $context->file->contents) !== 1) {
            return;
        }

        $tokens = PhpcsTokens::of($context->file);
        foreach ($tokens->tokens as $index => $token) {
            if ($token['code'] === 'T_DOUBLE_QUOTED_STRING' || $token['code'] === 'T_HEREDOC') {
                foreach ($token['embeds'] as [$name, $pos]) {
                    if (in_array('$' . $name, self::SUPERGLOBALS, strict: true)) {
                        $this->report(
                            $context,
                            new Span($pos, $pos + strlen($name) + 1),
                            "Detected usage of a non-sanitized, non-validated input variable {$name}.",
                            'InputNotValidatedNotSanitized',
                        );
                    }
                }
            } elseif (
                $token['code'] === 'T_VARIABLE'
                && in_array($token['content'], self::SUPERGLOBALS, strict: true)
            ) {
                $this->variable($context, $tokens, $index);
            }
        }
    }

    private function variable(LintContext $context, PhpcsTokens $tokens, int $index): void
    {
        if ($tokens->inUnset($index) || $tokens->isAssignment($index) || $tokens->inIssetOrEmpty($index)) {
            return;
        }

        $keys = $tokens->arrayAccessKeys($index);
        if ($keys === []) {
            return;
        }

        $variable = $tokens->content($index);
        $element = $variable . '[' . implode('][', $keys) . ']';
        [$start, $end] = $tokens->span($index);
        $span = new Span($start, $end);

        $validated =
            $tokens->code($tokens->afterAccess($index)) === 'T_COALESCE' || $this->isValidated($tokens, $index, $keys);
        if (!$validated) {
            $this->report(
                $context,
                $span,
                "Detected usage of a possibly undefined superglobal array index: {$element}.",
                'InputNotValidated',
                'Check that the array index exists before using it, with isset(), empty(), array_key_exists() or `??`.',
            );
        }

        if ($tokens->inTypeTest($index) || $tokens->isComparison($index, false) || $tokens->inArrayComparison($index)) {
            return;
        }

        $missingUnslash = function () use ($context, $variable, $element, $span): void {
            if (in_array($variable, self::SLASHED, strict: true)) {
                $this->report(
                    $context,
                    $span,
                    "{$element} not unslashed before sanitization.",
                    'MissingUnslash',
                    'Use wp_unslash() or similar.',
                );
            }
        };
        if (!$this->sanitization->isSanitized($tokens, $index, $missingUnslash)) {
            $this->report(
                $context,
                $span,
                "Detected usage of a non-sanitized input variable: {$element}.",
                'InputNotSanitized',
                'Pass it through a sanitizing function such as sanitize_text_field().',
            );
        }
    }

    /**
     * WPCS's `ValidationHelper::is_validated()`: an `isset()`, `empty()`, `array_key_exists()`
     * or `??` on the same element earlier in the function (or file) scope.
     *
     * @param list<string> $keys
     * @mago-expect lint:halstead
     */
    private function isValidated(PhpcsTokens $tokens, int $index, array $keys): bool
    {
        $variable = $tokens->content($index);
        $bare = array_map(PhpcsTokens::stripQuotes(...), $keys);
        $scopeStart = $tokens->functionOpener($index) ?? 0;
        for ($at = $scopeStart + 1; $at < $index; $at++) {
            $code = $tokens->code($at);
            $closer = $tokens->scopeCloser($at);
            if ($closer !== null && ($code !== 'T_FN' || $closer < $index)) {
                $at = $closer;
                continue;
            }

            if ($code === 'T_ISSET' || $code === 'T_EMPTY') {
                $closer = $tokens->code($at + 1) === '(' ? $tokens->closer($at + 1) : null;
                if ($closer === null) {
                    continue;
                }

                for ($at += 2; $at < $closer; $at++) {
                    if (
                        $tokens->code($at) === 'T_VARIABLE'
                        && $tokens->content($at) === $variable
                        && $this->sameKeys($tokens, $at, $bare)
                    ) {
                        return true;
                    }
                }
            } elseif ($code === 'T_STRING') {
                if ($this->keyExists($tokens, $at, $variable, $bare)) {
                    return true;
                }
            } elseif ($code === 'T_COALESCE' || $code === 'T_COALESCE_EQUAL') {
                $previous = $at;
                do {
                    $previous--;
                    if ($tokens->code($previous) === ']') {
                        $previous = (int) $tokens->closer($previous);
                        continue;
                    }

                    break;
                } while ($previous >= ($scopeStart + 1));

                if (
                    $tokens->code($previous) === 'T_VARIABLE'
                    && $tokens->content($previous) === $variable
                    && $this->sameKeys($tokens, $previous, $bare)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $bare
     */
    private function keyExists(PhpcsTokens $tokens, int $at, string $variable, array $bare): bool
    {
        if (
            (PhpcsTokens::KEY_EXISTS[strtolower($tokens->content($at))] ?? null) === null
            || $tokens->code($at + 1) !== '('
            || $tokens->hasObjectOperatorBefore($at)
            || $tokens->isNamespaced($at)
        ) {
            return false;
        }

        $array = $tokens->parameter($at, 2, 'array');
        if (
            $array === null
            || $tokens->code($array['start']) !== 'T_VARIABLE'
            || $tokens->content($array['start']) !== $variable
        ) {
            return false;
        }

        if ($bare === [] || $this->sameKeys($tokens, $array['start'], $bare)) {
            return true;
        }

        // `array_key_exists( 'key', $_GET['outer'] )` validates `$_GET['outer']['key']`.
        $last = array_pop($bare);
        $found = array_map(PhpcsTokens::stripQuotes(...), $tokens->arrayAccessKeys($array['start']));
        $key = $tokens->parameter($at, 1, 'key');

        return $key !== null && $bare === $found && PhpcsTokens::stripQuotes($key['raw']) === $last;
    }

    /**
     * Every key of the element read is on the checked one, at the same depth.
     *
     * @param list<string> $bare
     */
    private function sameKeys(PhpcsTokens $tokens, int $at, array $bare): bool
    {
        $found = array_map(PhpcsTokens::stripQuotes(...), $tokens->arrayAccessKeys($at));

        return $bare === [] || array_diff_assoc($bare, $found) === [];
    }

    private function report(LintContext $context, Span $span, string $message, string $code, ?string $help = null): void
    {
        $issue = Issue::new($message, $span);
        $this->report->issue($context, $help === null ? $issue : $issue->withHelp($help), [self::SNIFF . '.' . $code]);
    }
}
