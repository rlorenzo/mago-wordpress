<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

/**
 * `mago-wordpress convert-comments`: rewrites phpcs suppression comments, and pragmas that name
 * the Mago core rules this package supersedes, into `@mago-expect` pragmas. What each comment
 * covers comes from a real `mago lint` run with phpcs comments not honoured, not from the sniff
 * name, so a comment that covers nothing is not turned into a pragma that never fires.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @psalm-type Func = array{doc: null|array{int, int}, start: int, open: int, close: int}
 * @psalm-type Result = array{source: string, notes: list<array{int, string, string}>, claimed: array<string, int>, extra: array<string, int>}
 *
 * @internal
 */
final class CommentConversion
{
    /**
     * Set for a worker to ignore phpcs comments regardless of `honor-phpcs-comments`. Internal:
     * this command sets it for its own lint runs.
     */
    public const ENV = 'MAGO_WORDPRESS_IGNORE_PHPCS_COMMENTS';

    /** Mago core rule => the extension rule that replaces it. */
    public const SUPERSEDED = [
        'no-unescaped-output' => 'wordpress/escape-output',
        'validated-sanitized-input' => 'wordpress/validated-sanitized-input',
        'nonce-verification' => 'wordpress/nonce-verification',
        'use-wp-functions' => 'wordpress/alternative-functions',
        'prepared-sql' => 'wordpress/prepared-sql',
        'no-direct-db-query' => 'wordpress/direct-database-query',
        'no-db-schema-change' => 'wordpress/direct-database-query',
        'require-preg-quote-delimiter' => 'wordpress/preg-quote-delimiter',
    ];

    public const KINDS = ['converted', 'kept as plain comment', 'dropped', 'region', 'not convertible'];

    /** A line that ends a statement, so an own-line pragma covers no further. */
    private const STATEMENT_END = '/(;|\{|\}|\?>|[^-=]>|:)\s*$/';

    private const CODES = '(lint:[\w\/-]+(?:\(\d+\))?(?:\s*,\s*(?:lint:)?[\w\/-]+(?:\(\d+\))?)*)';

    /** @var array<int, list<string>> Lines to insert before a line. */
    private array $insert = [];

    /** @var list<array{int, string, string}> [line, kind, text] per comment. */
    private array $notes = [];

    /** @var array<int, array<string, int>> Every issue per line and rule. */
    private readonly array $issues;

    /** @var array<string, int> Issues the new pragmas cover, per rule. */
    private array $claimed = [];

    /** @var array<string, int> Claimed issues that phpcs comments did not already suppress, per rule. */
    private array $extra = [];

    /**
     * @param list<null|string> $lines the file's lines; null deletes one
     * @param array<int, array<string, int>> $pending issues per line and rule not yet covered
     * @param null|array<int, array<string, int>> $suppressed issues per line and rule that phpcs comments
     *     suppress; null when unknown (every issue then counts as suppressed)
     * @param list<Func> $functions
     * @param array<int, true> $html lines that start in inline HTML
     */
    private function __construct(
        private array $lines,
        private array $pending,
        private ?array $suppressed,
        private readonly array $functions,
        private readonly array $html,
    ) {
        $this->issues = $pending;
    }

    /**
     * Takes the pending issues on the lines whose rule passes the filter. For a phpcs comment,
     * an extension rule's issues are taken only as far as the comment really suppressed them
     * (the worker matches message codes; a rule here spans several).
     *
     * @param list<int> $lines
     * @param callable(string): bool $filter
     * @return array<string, int>
     */
    private function take(array $lines, callable $filter, bool $phpcs = false): array
    {
        $claim = [];
        foreach ($lines as $line) {
            foreach ($this->pending[$line] ?? [] as $rule => $count) {
                if (!$filter($rule)) {
                    continue;
                }

                $suppressed = $this->suppressed === null ? $count : $this->suppressed[$line][$rule] ?? 0;
                if ($phpcs && str_starts_with($rule, 'wordpress/')) {
                    $count = min($count, $suppressed);
                }

                if ($count === 0) {
                    continue;
                }

                $claim[$rule] = ($claim[$rule] ?? 0) + $count;
                $this->claimed[$rule] = ($this->claimed[$rule] ?? 0) + $count;
                $this->extra[$rule] = ($this->extra[$rule] ?? 0) + $count - min($count, $suppressed);
                $this->pending[$line][$rule] -= $count;
                if ($this->suppressed !== null) {
                    $this->suppressed[$line][$rule] = $suppressed - min($count, $suppressed);
                }

                if ($this->pending[$line][$rule] === 0) {
                    unset($this->pending[$line][$rule]);
                }
            }
        }

        return $claim;
    }

    private function note(int $line, string $kind, string $text): void
    {
        $this->notes[] = [$line, $kind, $text];
    }

    private function source(): string
    {
        $out = [];
        foreach ($this->lines as $index => $line) {
            foreach ($this->insert[$index + 1] ?? [] as $inserted) {
                $out[] = $inserted;
            }

            if ($line !== null) {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    /**
     * @param list<string> $args
     */
    public static function run(array $args, string $cwd): int
    {
        $write = in_array('--write', $args, strict: true);
        $paths = array_values(array_filter($args, static fn(string $arg): bool => !str_starts_with($arg, '--')));
        $mago = is_file("{$cwd}/vendor/bin/mago") ? "{$cwd}/vendor/bin/mago" : 'mago';

        $honored = self::lint($mago, $paths, ignorePhpcs: false);
        $before = self::lint($mago, $paths, ignorePhpcs: true);
        if ($before === null || $honored === null) {
            fwrite(STDERR, data: "mago lint did not return a JSON report.\n");

            return 1;
        }

        $counts = array_fill_keys(self::KINDS, value: 0);
        $claimed = [];
        $extra = [];
        foreach (self::files($mago, $paths, array_keys($before)) as $file) {
            $source = (string) file_get_contents("{$cwd}/{$file}");
            if (!preg_match('/phpcs:|@codingStandards|@mago-(?:expect|ignore)/i', $source)) {
                continue;
            }

            $result = self::convert($source, $before[$file] ?? [], $honored[$file] ?? []);
            foreach ($result['notes'] as [$line, $kind, $text]) {
                echo "{$file}:{$line}: {$kind}: {$text}\n";
                $counts[$kind]++;
            }

            if ($write && $result['source'] !== $source) {
                file_put_contents("{$cwd}/{$file}", $result['source']);
                $claimed[$file] = $result['claimed'];
                $extra[$file] = $result['extra'];
            }
        }

        echo "\n";
        foreach ($counts as $kind => $count) {
            echo "{$kind}: {$count}\n";
        }

        if (!$write) {
            echo "\nDry run: pass --write to apply.\n";

            return 0;
        }

        $after = self::lint($mago, $paths, ignorePhpcs: true);

        return $after === null ? 1 : self::verify($honored, $after, $claimed, $extra);
    }

    /**
     * Rewrites one file's comments. $issues are [line, rule] from a lint run that did not honour
     * phpcs comments, $honored from one that did (null: treat every issue as suppressed).
     *
     * @param list<array{int, string}> $issues
     * @param null|list<array{int, string}> $honored
     * @return Result
     */
    public static function convert(string $source, array $issues, ?array $honored = null): array
    {
        $pending = self::perLine($issues);
        $suppressed = null;
        if ($honored !== null) {
            $suppressed = $pending;
            foreach (self::perLine($honored) as $line => $rules) {
                foreach ($rules as $rule => $count) {
                    $suppressed[$line][$rule] = max(0, ($suppressed[$line][$rule] ?? 0) - $count);
                }
            }
        }

        $state = new self(
            explode("\n", $source),
            $pending,
            $suppressed,
            self::functions($source),
            self::htmlLines($source),
        );
        $regions = [];
        foreach (PhpcsSuppressions::commentLines($source) as [$line, $piece, $before, $after, $doc]) {
            $text = rtrim(ltrim($piece, characters: " \t/*#"), characters: " */\t\r\n");
            $own = !$before && !$after;
            if (preg_match('/^@?phpcs:(ignorefile|ignore|disable|enable)\b(.*)$/i', $text, $match) === 1) {
                $kind = strtolower($match[1]);
                [$codes, $reason] = self::split($match[2]);
                if ($kind === 'ignorefile') {
                    $state->note(
                        $line,
                        'not convertible',
                        "{$text} (no file-level pragma in Mago; exclude the file in mago.toml instead)",
                    );
                } elseif ($kind === 'ignore') {
                    $coverage = $own ? self::statement($state->lines, $line + 1) : [$line];
                    $claim = $state->take(
                        $coverage,
                        static fn(string $rule): bool => self::matches($codes, $rule),
                        phpcs: true,
                    );
                    self::replace($state, [$line, $piece, $own], $claim, $reason);
                } elseif ($kind === 'disable') {
                    $regions[] = [$line, $piece, $own, $codes, $reason];
                } else {
                    foreach ($regions as $index => $region) {
                        if ($codes === [] || self::reenables($codes, $region[3])) {
                            self::region($state, $region, [$line, $piece, $own]);
                            unset($regions[$index]);
                        }
                    }
                }

                continue;
            }

            if (str_contains($text, '@codingStandards')) {
                $state->note($line, 'not convertible', $text);

                continue;
            }

            if (preg_match('/@mago-(?:expect|ignore)\s+' . self::CODES . '(.*)$/', $text, $match) === 1) {
                self::superseded($state, [$line, $piece, $own, $doc], $match[1], $match[2]);
            }
        }

        // A disable without an enable runs to the end of the file.
        foreach ($regions as $region) {
            self::region($state, $region, null);
        }

        return [
            'source' => $state->source(),
            'notes' => $state->notes,
            'claimed' => $state->claimed,
            'extra' => $state->extra,
        ];
    }

    /**
     * @param list<array{int, string}> $issues
     * @return array<int, array<string, int>>
     */
    private static function perLine(array $issues): array
    {
        $perLine = [];
        foreach ($issues as [$line, $rule]) {
            if ($rule !== 'unfulfilled-expect') {
                $perLine[$line][$rule] = ($perLine[$line][$rule] ?? 0) + 1;
            }
        }

        return $perLine;
    }

    /**
     * Whether a `phpcs:enable` of $codes ends a disable of $disabled (each disabled code at or
     * under an enabled one).
     *
     * @param list<string> $codes
     * @param list<string> $disabled
     */
    private static function reenables(array $codes, array $disabled): bool
    {
        if ($disabled === []) {
            return false;
        }

        foreach ($disabled as $code) {
            $match = false;
            foreach ($codes as $enabled) {
                $match = $match || $code === $enabled || str_starts_with($code, "{$enabled}.");
            }

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{int, string, bool} $comment line, comment text, on its own line
     * @param array<string, int> $claim
     */
    private static function replace(self $state, array $comment, array $claim, string $reason): void
    {
        [$line, $piece, $own] = $comment;
        if ($claim === []) {
            self::retire($state, $line, $piece, $reason);

            return;
        }

        $pragma = '@mago-expect ' . self::codes($claim) . ($reason === '' ? '' : " -- {$reason}");
        $text = str_replace($piece, self::comment($piece, $pragma), (string) $state->lines[$line - 1]);
        // A one-line PHP block holding the pragma does not reach the inline HTML after it; a three-line block does.
        if ($own && preg_match('/^(\s*)<\?php\s+(.*?)\s*\?>\s*$/', $text, $html) === 1) {
            $text = "{$html[1]}<?php\n{$html[1]}{$html[2]}\n{$html[1]}?>";
        }

        $state->lines[$line - 1] = $text;
        $state->note($line, 'converted', $pragma);
    }

    /**
     * Removes a comment that covers nothing, keeping its reason as a plain comment.
     */
    private static function retire(self $state, int $line, string $piece, string $reason): void
    {
        $text = (string) $state->lines[$line - 1];
        if ($reason !== '') {
            $plain = ucfirst($reason) . (preg_match('/[.!?]$/', $reason) === 1 ? '' : '.');
            $state->lines[$line - 1] = str_replace($piece, self::comment($piece, $plain), $text);
            $state->note($line, 'kept as plain comment', $plain);

            return;
        }

        $text = preg_replace(
            '/\s+\?>/',
            replacement: ' ?>',
            subject: rtrim(str_replace($piece, replace: '', subject: $text)),
        );
        $state->lines[$line - 1] = preg_match('/^\s*(<\?php\s*\?>)?\s*$/', (string) $text) === 1
            ? null
            : (string) $text;
        $state->note($line, 'dropped', 'covers nothing');
    }

    /**
     * A phpcs:disable … phpcs:enable region (or to the end of the file): a pragma before each
     * statement in it that has issues, or one in a function's docblock when that covers exactly
     * the same issues.
     *
     * @param array{int, string, bool, list<string>, string} $disable
     * @param null|array{int, string, bool} $enable
     */
    private static function region(self $state, array $disable, ?array $enable): void
    {
        [$start, $piece, , $codes, $reason] = $disable;
        $end = $enable === null ? count($state->lines) + 1 : $enable[0];
        $byLine = [];
        for ($line = $start + 1; $line < $end; $line++) {
            $claim = $state->take([$line], static fn(string $rule): bool => self::matches($codes, $rule), phpcs: true);
            if ($claim !== []) {
                $byLine[$line] = $claim;
            }
        }

        $suffix = $reason === '' ? '' : " -- {$reason}";
        if ($enable !== null) {
            self::removeComment($state, $enable[0], $enable[1]);
        }

        if ($byLine === []) {
            self::retire($state, $start, $piece, $reason);

            return;
        }

        self::removeComment($state, $start, $piece);
        $statements = [];
        foreach ($byLine as $line => $claim) {
            $first = $line;
            for ($back = $line - 1; $back > $start; $back--) {
                $text = (string) $state->lines[$back - 1];
                // A comment inside a statement (a translators comment in an argument list) does not end it.
                if (preg_match('#^\s*(//|\#|/?\*)#', $text) === 1) {
                    continue;
                }

                if (self::endsStatement($text)) {
                    break;
                }

                $first = $back;
            }

            foreach ($claim as $rule => $count) {
                $statements[$first][$rule] = ($statements[$first][$rule] ?? 0) + $count;
            }
        }

        // One docblock pragma instead of several, when it covers exactly the region's issues.
        $function = count($statements) > 1 ? $state->wholeFunction($byLine) : null;
        if ($function !== null) {
            $pragma = '@mago-expect ' . self::codes(self::sum($byLine)) . $suffix;
            $state->docPragma($function, $pragma);
            $state->note($start, 'region', "{$pragma} in the docblock of the function on line {$function['start']}");

            return;
        }

        $placed = [];
        // Otherwise one per function wholly inside the region (a region to the end of the file covers many).
        foreach ($state->functionsWithin($start, $end, $statements) as [$function, $lines]) {
            $claim = self::sum(array_intersect_key($statements, array_flip($lines)));
            if (count($lines) < 2 || !$state->exact($function, $claim)) {
                continue;
            }

            $pragma = '@mago-expect ' . self::codes($claim) . $suffix;
            $state->docPragma($function, $pragma);
            $placed[] = "{$pragma} in the docblock of the function on line {$function['start']}";
            $statements = array_diff_key($statements, array_flip($lines));
        }

        foreach ($statements as $line => $claim) {
            $pragma = '@mago-expect ' . self::codes($claim) . $suffix;
            $comment = self::indent((string) $state->lines[$line - 1]) . "// {$pragma}";
            // Before inline HTML: a three-line PHP block; its close tag eats the newline, so the output is unchanged.
            $state->insert[$line][] = array_key_exists($line, $state->html) ? "<?php\n{$comment}\n?>" : $comment;
            $placed[] = "{$pragma} before line {$line}";
        }

        $state->note($start, 'region', implode('; ', $placed));
    }

    /**
     * Retargets a `@mago-expect`/`@mago-ignore` that names a superseded core rule, recounted.
     *
     * @param array{int, string, bool, bool} $comment line, comment text, on its own line, in a docblock
     */
    private static function superseded(self $state, array $comment, string $codes, string $rest): void
    {
        [$line, $piece, $own, $doc] = $comment;
        preg_match_all('/(?:lint:)?([\w\/-]+)(\(\d+\))?/', $codes, $items, PREG_SET_ORDER);
        $targets = [];
        foreach ($items as $item) {
            if (array_key_exists($item[1], self::SUPERSEDED)) {
                $targets[self::SUPERSEDED[$item[1]]] = true;
            }
        }

        if ($targets === []) {
            return;
        }

        $coverage = [$line];
        if ($doc) {
            foreach ($state->functions as $function) {
                if ($function['doc'] !== null && $function['doc'][0] <= $line && $line <= $function['doc'][1]) {
                    $coverage = range($function['start'], $function['close']);
                }
            }
        } elseif ($own) {
            $coverage = self::statement($state->lines, $line + 1);
        }

        $claim = $state->take($coverage, static fn(string $rule): bool => array_key_exists($rule, $targets));
        $kept = [];
        foreach ($items as $item) {
            if (!array_key_exists($item[1], self::SUPERSEDED)) {
                $kept[] = 'lint:' . $item[1] . ($item[2] ?? '');
            }
        }

        $new = $claim === [] ? implode(', ', $kept) : implode(', ', [...$kept, self::codes($claim)]);
        $text = (string) $state->lines[$line - 1];
        if ($new !== '') {
            $state->lines[$line - 1] = str_replace($codes, $new, $text);
            $state->note($line, 'converted', "{$codes} -> {$new}");

            return;
        }

        $reason = trim(ltrim(trim($rest), characters: '-'));
        if ($doc) {
            $state->lines[$line - 1] = null;
            $state->note($line, 'dropped', "{$codes} covers nothing");

            return;
        }

        self::retire($state, $line, $piece, $reason);
    }

    /**
     * A comment in the style of $piece, keeping the whitespace that followed it.
     */
    private static function comment(string $piece, string $text): string
    {
        $comment = str_starts_with($piece, '/*') ? "/* {$text} */" : "// {$text}";

        return $comment . substr($piece, strlen(rtrim($piece)));
    }

    private static function removeComment(self $state, int $line, string $piece): void
    {
        $text = rtrim(str_replace($piece, replace: '', subject: (string) $state->lines[$line - 1]));
        $state->lines[$line - 1] = trim($text) === '' ? null : $text;
    }

    /**
     * The innermost function that holds every issue of the region and no other issue of those
     * rules, so a docblock pragma covers exactly the region's issues.
     *
     * @param array<int, array<string, int>> $byLine
     * @return null|Func
     */
    private function wholeFunction(array $byLine): ?array
    {
        $rules = self::sum($byLine);
        $best = null;
        foreach ($this->functions as $function) {
            if (
                min(array_keys($byLine)) < $function['open']
                || max(array_keys($byLine)) > $function['close']
                || !$this->exact($function, $rules)
            ) {
                continue;
            }

            if ($best === null || ($function['close'] - $function['open']) < ($best['close'] - $best['open'])) {
                $best = $function;
            }
        }

        return $best;
    }

    /**
     * Whether the function's issues of the claimed rules are exactly the claim.
     *
     * @param Func $function
     * @param array<string, int> $claim
     */
    private function exact(array $function, array $claim): bool
    {
        $found = [];
        for ($line = $function['start']; $line <= $function['close']; $line++) {
            foreach (array_intersect_key($this->issues[$line] ?? [], $claim) as $rule => $count) {
                $found[$rule] = ($found[$rule] ?? 0) + $count;
            }
        }

        ksort($found);
        ksort($claim);

        return $found === $claim;
    }

    /**
     * The outermost functions wholly between the lines $start and $end, each with the statement
     * lines it holds.
     *
     * @param array<int, array<string, int>> $statements
     * @return list<array{Func, list<int>}>
     */
    private function functionsWithin(int $start, int $end, array $statements): array
    {
        $within = array_filter(
            $this->functions,
            static fn(array $function): bool => $function['start'] > $start && $function['close'] < $end,
        );
        $groups = [];
        foreach ($within as $function) {
            foreach ($within as $outer) {
                if (
                    $outer !== $function
                    && $outer['start'] <= $function['start']
                    && $function['close'] <= $outer['close']
                ) {
                    continue 2;
                }
            }

            $lines = array_values(array_filter(
                array_keys($statements),
                static fn(int $line): bool => $function['start'] <= $line && $line <= $function['close'],
            ));
            if ($lines !== []) {
                $groups[] = [$function, $lines];
            }
        }

        return $groups;
    }

    /**
     * Adds a pragma to the function's docblock, or a new docblock holding it.
     *
     * @param Func $function
     */
    private function docPragma(array $function, string $pragma): void
    {
        if ($function['doc'] !== null) {
            $close = $function['doc'][1];
            $text = (string) $this->lines[$close - 1];
            $prefix = substr($text, offset: 0, length: (int) strpos($text, needle: '*'));
            $this->insert[$close][] = "{$prefix}* {$pragma}";

            return;
        }

        $indent = self::indent((string) $this->lines[$function['start'] - 1]);
        $this->insert[$function['start']][] = "{$indent}/**\n{$indent} * {$pragma}\n{$indent} */";
    }

    /**
     * The lines of the statement starting at $line, by a line-ending heuristic.
     *
     * @param list<null|string> $lines
     * @return list<int>
     */
    private static function statement(array $lines, int $line): array
    {
        $covered = [$line];
        while (
            $line <= count($lines)
            && count($covered) < 30
            && preg_match(self::STATEMENT_END, (string) $lines[$line - 1]) !== 1
        ) {
            $covered[] = ++$line;
        }

        return $covered;
    }

    /**
     * Whether a phpcs code list (empty: every sniff) names a sniff the rule ports.
     *
     * @param list<string> $codes
     */
    private static function matches(array $codes, string $rule): bool
    {
        foreach (SniffMap::RULES as $sniff => $rules) {
            if (!in_array($rule, $rules, strict: true)) {
                continue;
            }

            if ($codes === []) {
                return true;
            }

            foreach ($codes as $code) {
                // A Mago core rule has no WPCS message codes, so only a sniff or broader code is known to cover it.
                $message = str_starts_with($code, "{$sniff}.") && str_starts_with($rule, 'wordpress/');
                if ($code === $sniff || $message || str_starts_with($sniff, "{$code}.")) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{list<string>, string} the codes and the reason after ` --`
     */
    private static function split(string $text): array
    {
        $parts = explode('--', $text, limit: 2);

        return [PhpcsSuppressions::codes($parts[0]), trim($parts[1] ?? '')];
    }

    /**
     * @param array<string, int> $claim
     */
    private static function codes(array $claim): string
    {
        $codes = [];
        foreach ($claim as $rule => $count) {
            $codes[] = "lint:{$rule}" . ($count > 1 ? "({$count})" : '');
        }

        return implode(', ', $codes);
    }

    /**
     * @param array<int, array<string, int>> $byLine
     * @return array<string, int>
     */
    private static function sum(array $byLine): array
    {
        $sum = [];
        foreach ($byLine as $claim) {
            foreach ($claim as $rule => $count) {
                $sum[$rule] = ($sum[$rule] ?? 0) + $count;
            }
        }

        return $sum;
    }

    /**
     * Whether a statement cannot continue past the line: it ends one, is blank, or is a comment.
     */
    private static function endsStatement(string $line): bool
    {
        $line = trim($line);

        return (
            $line === ''
            || preg_match('#^(//|\#|/?\*)#', $line) === 1
            || preg_match(self::STATEMENT_END, $line) === 1
            || preg_match('/<\?php$/i', $line) === 1
        );
    }

    private static function indent(string $line): string
    {
        return substr($line, offset: 0, length: strlen($line) - strlen(ltrim($line)));
    }

    /**
     * The lines that start inside inline HTML.
     *
     * @return array<int, true>
     */
    private static function htmlLines(string $source): array
    {
        $html = [];
        $line = 1;
        $lineStart = true;
        foreach (token_get_all($source) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if (is_array($token) && $token[0] === T_INLINE_HTML) {
                if ($lineStart) {
                    $html[$line] = true;
                }

                // Each newline inside the HTML with more HTML after it starts an HTML line.
                $breaks = substr_count(rtrim($text, characters: "\n"), needle: "\n");
                for ($index = 1; $index <= $breaks; $index++) {
                    $html[$line + $index] = true;
                }
            }

            $line += substr_count($text, needle: "\n");
            $lineStart = str_ends_with($text, needle: "\n");
        }

        return $html;
    }

    /**
     * Functions, methods and documented closures with their docblock, first line and body braces.
     *
     * @return list<Func>
     */
    private static function functions(string $source): array
    {
        $tokens = token_get_all($source);
        $lines = [];
        $line = 1;
        foreach ($tokens as $index => $token) {
            $lines[$index] = $line;
            $line += substr_count(is_array($token) ? $token[1] : $token, needle: "\n");
        }

        $functions = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $next = $index + 1;
            while (
                array_key_exists($next, $tokens)
                && (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE || $tokens[$next] === '&')
            ) {
                $next++;
            }

            $named = is_array($tokens[$next] ?? null) && $tokens[$next][0] === T_STRING;

            $open = $next;
            while (array_key_exists($open, $tokens) && $tokens[$open] !== '{' && $tokens[$open] !== ';') {
                $open++;
            }

            if (($tokens[$open] ?? ';') === ';') {
                continue;
            }

            $depth = 0;
            for ($close = $open; array_key_exists($close, $tokens); $close++) {
                $text = is_array($tokens[$close]) ? $tokens[$close][1] : $tokens[$close];
                if (
                    $text === '{'
                    || is_array($tokens[$close])
                    && in_array($tokens[$close][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], strict: true)
                ) {
                    $depth++;
                } elseif ($text === '}' && --$depth === 0) {
                    break;
                }
            }

            $start = $index;
            for (
                $back = $index - 1;
                $back >= 0
                && is_array($tokens[$back])
                && in_array(
                    $tokens[$back][0],
                    [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL],
                    strict: true,
                );
                $back--
            ) {
                if ($tokens[$back][0] !== T_WHITESPACE) {
                    $start = $back;
                }
            }

            $doc = null;
            if ($back >= 0 && is_array($tokens[$back]) && $tokens[$back][0] === T_DOC_COMMENT) {
                $doc = [$lines[$back], $lines[$back] + substr_count($tokens[$back][1], needle: "\n")];
            }

            $doc = $doc !== null && $doc[0] === $doc[1] ? null : $doc;
            // A closure takes a pragma only in its own docblock; a new one would document the enclosing statement.
            if (!$named && $doc === null) {
                continue;
            }

            $functions[] = [
                'doc' => $doc,
                'start' => $lines[$start],
                'open' => $lines[$open],
                'close' => $lines[$close] ?? $line,
            ];
        }

        return $functions;
    }

    /**
     * Issues per file as [line, rule], or null when mago returned no report. Run in $cwd when
     * given, else in the current directory.
     *
     * @param list<string> $paths
     * @return null|array<string, list<array{int, string}>>
     */
    public static function lint(string $mago, array $paths, bool $ignorePhpcs, string $cwd = ''): ?array
    {
        putenv($ignorePhpcs ? self::ENV . '=1' : self::ENV);
        $command =
            ($cwd === '' ? '' : 'cd ' . escapeshellarg($cwd) . ' && ')
            . escapeshellarg($mago)
            . ' lint --ignore-baseline --reporting-format json';
        foreach ($paths as $path) {
            $command .= ' ' . escapeshellarg($path);
        }

        $report = Shape::arrayAt(
            json_decode((string) shell_exec("{$command} 2>/dev/null"), associative: true),
            'issues',
        );
        if ($report === null) {
            return null;
        }

        $issues = [];
        foreach ($report as $issue) {
            $span = Shape::arrayAt((Shape::arrayAt($issue, 'annotations') ?? [])[0] ?? null, 'span');
            $file = Shape::string(Shape::arrayAt($span, 'file_id')['name'] ?? null);
            $start = Shape::arrayAt($span, 'start')['line'] ?? null;
            $code = Shape::string(Shape::array($issue)['code'] ?? null);
            if ($file !== null && is_int($start) && $code !== null) {
                $issues[$file][] = [$start + 1, $code];
            }
        }

        return $issues;
    }

    /**
     * The files mago lints, limited to the given paths, plus those the lint run reported on
     * (`list-files` knows only the configured source paths).
     *
     * @param list<string> $paths
     * @param list<string> $reported
     * @return list<string>
     */
    private static function files(string $mago, array $paths, array $reported): array
    {
        $files = $reported;
        foreach (explode(
            "\n",
            (string) shell_exec(escapeshellarg($mago) . ' list-files --command linter 2>/dev/null'),
        ) as $file) {
            $file = trim($file);
            if ($file === '') {
                continue;
            }

            foreach ($paths === [] ? [''] : $paths as $path) {
                $path = trim(preg_replace('#^\./#', replacement: '', subject: $path) ?? $path, characters: '/');
                if ($path === '' || $path === '.' || $file === $path || str_starts_with($file, "{$path}/")) {
                    $files[] = $file;
                    break;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * After a write: with phpcs comments ignored, every file has the issues it had with them
     * honoured, less what the new pragmas cover beyond them, and no `unfulfilled-expect`.
     * Anything else is a phpcs comment that still suppresses an issue.
     *
     * @param array<string, list<array{int, string}>> $honored
     * @param array<string, list<array{int, string}>> $after
     * @param array<string, array<string, int>> $claimed
     * @param array<string, array<string, int>> $extra
     */
    private static function verify(array $honored, array $after, array $claimed, array $extra): int
    {
        $problems = [];
        foreach (array_keys($honored + $after) as $file) {
            $expected = [];
            foreach ($honored[$file] ?? [] as [, $rule]) {
                $expected[$rule] = ($expected[$rule] ?? 0) + 1;
            }

            foreach ($extra[$file] ?? [] as $rule => $count) {
                $expected[$rule] = ($expected[$rule] ?? 0) - $count;
            }

            unset($expected['unfulfilled-expect']);
            $actual = [];
            foreach ($after[$file] ?? [] as [$line, $rule]) {
                $actual[$rule] = ($actual[$rule] ?? 0) + 1;
                if ($rule === 'unfulfilled-expect' && array_key_exists($file, $claimed)) {
                    $problems[] = "{$file}:{$line}: unfulfilled-expect";
                }
            }

            unset($actual['unfulfilled-expect']);
            foreach ($expected + $actual as $rule => $_) {
                $more = ($actual[$rule] ?? 0) - ($expected[$rule] ?? 0);
                if ($more > 0) {
                    $problems[] = "{$file}: {$rule}: {$more} still suppressed only by a phpcs comment";
                } elseif ($more < 0) {
                    $problems[] = "{$file}: {$rule}: " . -$more . ' fewer than with phpcs comments honoured';
                }
            }
        }

        if ($problems === []) {
            $done = "\nNo phpcs comment suppresses anything any more. Set \"honor-phpcs-comments\": false in\n";
            echo $done . "composer.json extra.mago-wordpress.\n";

            return 0;
        }

        foreach ($problems as $problem) {
            fwrite(STDERR, "{$problem}\n");
        }

        $keep = "\nKeep \"honor-phpcs-comments\" on: the phpcs comments behind the lines above (see the\n";
        echo $keep . "\"not convertible\" notes) still suppress issues. Replace them by hand first.\n";

        return 1;
    }
}
