<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Closure;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function array_fill_keys;
use function count;
use function strtolower;

/**
 * WPCS's `SanitizationHelperTrait`, over `PhpcsTokens`, with the project's
 * `custom-sanitizing-functions` and `custom-unslashing-sanitizing-functions`.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class Sanitization
{
    /** @var array<string, true> */
    private readonly array $sanitizing;

    /** @var array<string, true> */
    private readonly array $unslashingSanitizing;

    public function __construct(Settings $settings)
    {
        $this->sanitizing = array_fill_keys([
            ...Lists::SANITIZING_FUNCTIONS,
            ...$settings->customList('custom-sanitizing-functions'),
        ], value: true);
        $this->unslashingSanitizing = array_fill_keys([
            ...Lists::UNSLASHING_SANITIZING_FUNCTIONS,
            ...$settings->customList('custom-unslashing-sanitizing-functions'),
        ], value: true);
    }

    /** `is_only_sanitized()`: sanitized, and nothing else is done with the value. */
    public function isOnlySanitized(PhpcsTokens $tokens, int $index): bool
    {
        if (!$this->isSanitized($tokens, $index)) {
            return false;
        }

        $nested = $tokens->nested($index);
        if ($nested === []) {
            return true;
        }

        return !$tokens->isSafeCasted($index) && count($nested) === 1;
    }

    /**
     * `is_sanitized()`: the variable is cast to a safe type, or is passed straight to a
     * sanitizing function. `$missingUnslash` is called when it is not unslashed first.
     *
     * @param null|Closure(int): void $missingUnslash
     */
    public function isSanitized(PhpcsTokens $tokens, int $index, ?Closure $missingUnslash = null): bool
    {
        if ($tokens->inUnset($index) || $tokens->isSafeCasted($index)) {
            return true;
        }

        if ($tokens->nested($index) === []) {
            if ($missingUnslash !== null) {
                $missingUnslash($index);
            }

            return false;
        }

        $walking = array_fill_keys(Lists::ARRAY_WALKING_FUNCTIONS, value: true);
        $unslashing = array_fill_keys(Lists::UNSLASHING_FUNCTIONS, value: true);
        $sanitizing = $this->sanitizing + $this->unslashingSanitizing + $walking;
        $function = $tokens->inFunctionCall($index, $sanitizing + $unslashing);
        if ($function === null) {
            if ($missingUnslash !== null) {
                $missingUnslash($index);
            }

            return false;
        }

        $name = strtolower($tokens->content($function));
        $unslashed = false;
        if (($unslashing[$name] ?? null) !== null) {
            $unslashed = true;
            $function = $tokens->inFunctionCall($function, $sanitizing);
            if ($function === null) {
                return false;
            }

            $name = strtolower($tokens->content($function));
        }

        if (($walking[$name] ?? null) !== null) {
            // The callback a string names is the function that sanitizes each element.
            $callback = $tokens->parameter($function, $name === 'map_deep' ? 2 : 1, 'callback');
            if ($callback !== null && $tokens->code($callback['start']) === 'T_CONSTANT_ENCAPSED_STRING') {
                $name = strtolower(PhpcsTokens::stripQuotes($tokens->content($callback['start'])));
            }
        }

        if (!$unslashed && $missingUnslash !== null && ($this->unslashingSanitizing[$name] ?? null) === null) {
            $missingUnslash($index);
        }

        return ($this->sanitizing[$name] ?? null) !== null || ($this->unslashingSanitizing[$name] ?? null) !== null;
    }
}
