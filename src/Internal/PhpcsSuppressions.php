<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_filter;
use function array_intersect;
use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function implode;
use function is_array;
use function ltrim;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function stripos;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function substr_count;
use function token_get_all;
use function trim;

use const ARRAY_FILTER_USE_KEY;
use const T_CLOSE_TAG;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_INLINE_HTML;
use const T_OPEN_TAG;
use const T_WHITESPACE;

/**
 * The lines of one file that phpcs suppression comments silence.
 *
 * Mirrors PHP_CodeSniffer 3.13 (`Tokenizer::createPositionMap()` and
 * `File::addMessage()`): `phpcs:ignore` silences its own line when it trails
 * code and the next line otherwise, `phpcs:disable` and `phpcs:enable` bound
 * a region, `phpcs:ignoreFile` silences the file, and the legacy
 * `@codingStandardsIgnore*` comments do the same. A code list matches a
 * reported code at dot boundaries, so a standard, category, sniff or message
 * code all work.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @psalm-type Rules = array{ignored: array<string, true>, except: array<string, true>}
 *
 * @internal
 */
final class PhpcsSuppressions
{
    private const ALL = '.all';

    private bool $ignoreFile = false;

    /** @var array<int, null|Rules> Rules set on a line; null re-enables it. */
    private array $lines = [];

    /** @var list<int> Lines where the region's rules change, in order. */
    private array $regionLines = [];

    /** @var list<null|Rules> The region's rules from the line after the matching line onward. */
    private array $regionRules = [];

    /** @var null|list<int> Offsets where each line starts, built on the first offset lookup. */
    private ?array $lineStarts = null;

    /** @var null|Rules */
    private ?array $ignoring = null;

    /**
     * Each phpcs:disable (true) and phpcs:enable (false) in order, with its
     * codes; an enable outside a region has none, as in phpcs.
     *
     * @var list<array{bool, list<string>}>
     */
    private array $toggles = [];

    private function __construct(
        private readonly string $source,
    ) {}

    public static function fromSource(string $source): self
    {
        $suppressions = new self($source);
        if (stripos($source, needle: 'phpcs:') === false && !str_contains($source, '@codingStandards')) {
            return $suppressions;
        }

        foreach (self::commentLines($source) as [$line, $text, $before, $after, $doc]) {
            $suppressions->apply($line, $text, $before, $after, $doc);
        }

        return $suppressions;
    }

    /**
     * Whether no comment silences anything, so no report needs a line lookup.
     */
    public function isEmpty(): bool
    {
        return !$this->ignoreFile && $this->lines === [] && $this->regionLines === [];
    }

    /**
     * Whether a report starting at the byte offset is silenced for any of the codes.
     *
     * @param list<string> $codes
     */
    public function isSuppressedAt(int $offset, array $codes): bool
    {
        if ($this->lineStarts === null) {
            $this->lineStarts = [0];
            $newline = -1;
            while (($newline = strpos($this->source, needle: "\n", offset: $newline + 1)) !== false) {
                $this->lineStarts[] = $newline + 1;
            }
        }

        return $this->isSuppressed(self::floorIndex($this->lineStarts, $offset) + 1, $codes);
    }

    /**
     * Whether a report on the line is silenced for any of the codes. A code
     * is the WPCS message code (`WordPress.WP.I18n.MissingTranslatorsComment`),
     * or only the sniff for a report WPCS has no message for.
     *
     * @param list<string> $codes
     */
    public function isSuppressed(int $line, array $codes): bool
    {
        if ($this->ignoreFile) {
            return true;
        }

        $rules = array_key_exists($line, $this->lines) ? $this->lines[$line] : $this->regionAt($line);
        if ($rules === null) {
            return false;
        }

        if ($rules['ignored'][self::ALL] ?? false) {
            return true;
        }

        foreach ($codes as $code) {
            if (self::matches($rules['ignored'], $code) && !self::matches($rules['except'], $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a phpcs:disable of a code's sniff, category or standard has no
     * later phpcs:enable of one of those. `WordPress.Files.FileName` checks
     * this itself, because its reports concern the whole file.
     *
     * @param list<string> $codes
     */
    public function disablesToEnd(array $codes): bool
    {
        $names = [];
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            for ($length = 1; $length <= 3 && $length <= count($parts); $length++) {
                $names[] = implode('.', array_slice($parts, offset: 0, length: $length));
            }
        }

        $disabled = false;
        foreach ($this->toggles as [$disable, $toggled]) {
            // Inside a disable only an enable counts, and outside one only a disable.
            if ($disable === $disabled || $toggled !== [] && array_intersect($names, $toggled) === []) {
                continue;
            }

            $disabled = $disable;
        }

        return $disabled;
    }

    /**
     * Every physical line of every comment, as [line, text, code before it on
     * the line, code after it on the line, in a docblock]. As in phpcs, the
     * docblock delimiters count as code, and the stars do not.
     *
     * @return list<array{int, string, bool, bool, bool}>
     */
    private static function commentLines(string $source): array
    {
        $tokens = token_get_all($source);
        $comments = [];
        $line = 1;
        $contentOnLine = false;
        foreach ($tokens as $index => $token) {
            [$id, $text] = is_array($token) ? [$token[0], $token[1]] : [null, $token];
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                $doc = $id === T_DOC_COMMENT;
                $pieces = explode(separator: "\n", string: $text);
                $last = count($pieces) - 1;
                foreach ($pieces as $offset => $piece) {
                    $comments[] = [
                        $line + $offset,
                        $piece,
                        $offset === 0 && ($doc || $contentOnLine),
                        $offset === $last && ($doc || self::contentFollows($tokens, $index)),
                        $doc,
                    ];
                }
            }

            $newlines = substr_count($text, needle: "\n");
            $isContent = self::isContent($id, $text);
            if ($newlines === 0) {
                $contentOnLine = $contentOnLine || $isContent;

                continue;
            }

            $line += $newlines;
            $contentOnLine = $isContent && trim(substr($text, offset: (int) strrpos($text, needle: "\n") + 1)) !== '';
        }

        return $comments;
    }

    /**
     * @param list<string|array{0: int, 1: string, 2: int}> $tokens
     */
    private static function contentFollows(array $tokens, int $index): bool
    {
        for ($next = $index + 1; $next < count($tokens); $next++) {
            $token = $tokens[$next];
            [$id, $text] = is_array($token) ? [$token[0], $token[1]] : [null, $token];
            if (str_contains($text, "\n") && !self::isContent($id, $text)) {
                return false;
            }

            if ($id === T_CLOSE_TAG) {
                return false;
            }

            if (self::isContent($id, $text)) {
                return true;
            }
        }

        return false;
    }

    private static function isContent(?int $id, string $text): bool
    {
        return match ($id) {
            T_WHITESPACE, T_OPEN_TAG => false,
            T_INLINE_HTML => trim($text) !== '',
            default => true,
        };
    }

    private function apply(int $line, string $piece, bool $before, bool $after, bool $doc): void
    {
        $text = rtrim(ltrim($piece, characters: " \t/*#"), characters: " */\t\r\n");
        $lower = strtolower($text);
        $legacy = str_contains($text, '@codingStandards');
        if (!$legacy && !str_starts_with($lower, 'phpcs:') && !str_starts_with($lower, '@phpcs:')) {
            // An ordinary comment changes nothing, so it records nothing.
            return;
        }

        $this->fillFromRegion($line);
        $this->applyComment($line, $text, $before, $after, $doc);

        // Again, for a trailing directive that just opened a region.
        $this->fillFromRegion($line);
        $this->recordRegion($line);
    }

    /**
     * Records the region's rules after the line, when they changed.
     */
    private function recordRegion(int $line): void
    {
        $last = count($this->regionLines) - 1;
        if ($last >= 0 && $this->regionLines[$last] === $line) {
            $this->regionRules[$last] = $this->ignoring;

            return;
        }

        if (($last >= 0 ? $this->regionRules[$last] : null) !== $this->ignoring) {
            $this->regionLines[] = $line;
            $this->regionRules[] = $this->ignoring;
        }
    }

    private function applyComment(int $line, string $text, bool $before, bool $after, bool $doc): void
    {
        if (str_contains($text, '@codingStandards')) {
            $this->applyLegacy($line, $text, ownLine: !$before && !$doc);

            return;
        }

        $text = ltrim($text, characters: '@');
        $note = strpos($text, needle: ' --');
        $this->applyDirective(
            $line,
            $note === false ? $text : substr($text, offset: 0, length: $note),
            ownLine: !$before && !$after,
        );
    }

    /**
     * Gives a line with no rules of its own the open region's rules.
     */
    private function fillFromRegion(int $line): void
    {
        if ($this->ignoring !== null && ($this->lines[$line] ?? null) === null) {
            $this->lines[$line] = $this->ignoring;
        }
    }

    /**
     * @mago-expect lint:no-boolean-flag-parameter $ownLine is where the comment sits, not a mode switch.
     */
    private function applyLegacy(int $line, string $text, bool $ownLine): void
    {
        $all = self::rules([self::ALL]);
        if (str_contains($text, '@codingStandardsIgnoreFile')) {
            $this->ignoreFile = true;

            return;
        }

        if ($this->ignoring === null && str_contains($text, '@codingStandardsIgnoreStart')) {
            $this->ignoring = $all;
            if ($ownLine) {
                $this->lines[$line] = $all;
            }

            return;
        }

        if ($this->ignoring !== null && str_contains($text, '@codingStandardsIgnoreEnd')) {
            $this->lines[$line] = $ownLine ? $all : $this->ignoring;
            $this->ignoring = null;

            return;
        }

        if ($this->ignoring === null && str_contains($text, '@codingStandardsIgnoreLine')) {
            $this->lines[$line] = $all;
            if ($ownLine) {
                $this->lines[$line + 1] = $all;
            }
        }
    }

    /**
     * @mago-expect lint:no-boolean-flag-parameter $ownLine is where the comment sits, not a mode switch.
     */
    private function applyDirective(int $line, string $text, bool $ownLine): void
    {
        $lower = strtolower($text);
        if (str_starts_with($lower, 'phpcs:ignorefile')) {
            $this->ignoreFile = true;

            return;
        }

        if (str_starts_with($lower, 'phpcs:disable')) {
            $codes = self::codes(substr($text, offset: 14));
            $this->toggles[] = [true, $codes];
            $this->disable($codes);
            if ($ownLine) {
                $this->lines[$line] = self::rules([self::ALL]);
            }

            return;
        }

        if (str_starts_with($lower, 'phpcs:enable')) {
            $codes = $this->ignoring === null ? [] : self::codes(substr($text, offset: 13));
            $this->toggles[] = [false, $codes];
            if ($this->ignoring !== null) {
                $this->enable($codes);
                $this->lines[$line] = $ownLine ? self::rules([self::ALL]) : $this->ignoring;
            }

            return;
        }

        if (str_starts_with($lower, 'phpcs:ignore')) {
            $codes = self::codes(substr($text, offset: 13));
            $rules = self::rules($codes === [] ? [self::ALL] : $codes);
            if ($this->ignoring !== null) {
                $rules = [
                    'ignored' => $rules['ignored'] + $this->ignoring['ignored'],
                    'except' => $this->ignoring['except'],
                ];
            }

            $this->lines[$line] = $ownLine ? self::rules([self::ALL]) : $rules;
            if ($ownLine) {
                $this->lines[$line + 1] = $rules;
            }
        }
    }

    /**
     * @param list<string> $codes
     */
    private function disable(array $codes): void
    {
        if ($codes === []) {
            $this->ignoring = self::rules([self::ALL]);

            return;
        }

        $ignoring = $this->ignoring ?? self::rules([]);
        foreach ($codes as $code) {
            $ignoring['ignored'][$code] = true;
            $ignoring['except'] = self::without($ignoring['except'], $code);
        }

        $this->ignoring = $ignoring;
    }

    /**
     * @param list<string> $codes
     */
    private function enable(array $codes): void
    {
        if ($codes === [] || $this->ignoring === null) {
            $this->ignoring = null;

            return;
        }

        $ignoring = $this->ignoring;
        foreach ($codes as $code) {
            $ignoring['ignored'] = self::without($ignoring['ignored'], $code);
            $ignoring['except'] = self::without($ignoring['except'], $code);
        }

        // As in phpcs, a region that had exceptions stays open with only exceptions.
        if ($ignoring['ignored'] === [] && $this->ignoring['except'] === []) {
            $this->ignoring = null;

            return;
        }

        foreach ($codes as $code) {
            $ignoring['except'][$code] = true;
        }

        $this->ignoring = $ignoring;
    }

    /**
     * @return null|Rules
     */
    private function regionAt(int $line): ?array
    {
        $index = self::floorIndex($this->regionLines, $line - 1);

        return $index < 0 ? null : $this->regionRules[$index];
    }

    /**
     * The index of the last value no greater than $value, or -1.
     *
     * @param list<int> $sorted
     */
    private static function floorIndex(array $sorted, int $value): int
    {
        $low = 0;
        $high = count($sorted) - 1;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            if ($sorted[$middle] <= $value) {
                $low = $middle + 1;

                continue;
            }

            $high = $middle - 1;
        }

        return $high;
    }

    /**
     * @return list<string>
     */
    private static function codes(string $list): array
    {
        $codes = [];
        foreach (explode(',', $list) as $code) {
            $code = trim($code);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param list<string> $ignored
     * @return Rules
     */
    private static function rules(array $ignored): array
    {
        $rules = ['ignored' => [], 'except' => []];
        foreach ($ignored as $code) {
            $rules['ignored'][$code] = true;
        }

        return $rules;
    }

    /**
     * The set without the code and every code under it.
     *
     * @param array<string, true> $set
     * @return array<string, true>
     */
    private static function without(array $set, string $code): array
    {
        return array_filter(
            $set,
            static fn(string $key): bool => $key !== $code && !str_starts_with($key, $code . '.'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Whether the set names the code, its sniff, category or standard.
     *
     * @param array<string, true> $set
     */
    private static function matches(array $set, string $code): bool
    {
        $parts = explode('.', $code);
        for ($length = count($parts); $length > 0; $length--) {
            if ($set[implode('.', array_slice($parts, offset: 0, length: $length))] ?? false) {
                return true;
            }
        }

        return false;
    }
}
