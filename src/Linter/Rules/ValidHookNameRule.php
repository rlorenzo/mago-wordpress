<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_map;
use function chr;
use function hexdec;
use function implode;
use function in_array;
use function octdec;
use function ord;
use function preg_replace_callback;
use function str_starts_with;
use function strlen;
use function strtr;
use function substr;

/**
 * Ports `WordPress.NamingConventions.ValidHookName`.
 *
 * Only checks the four hook-defining calls the Rust spec covers
 * (`do_action`, `apply_filters`, `do_action_ref_array`,
 * `apply_filters_ref_array`). `Lists::HOOK_INVOKE_FUNCTIONS` also lists
 * `do_action_deprecated` and `apply_filters_deprecated`, which the Rust
 * rule deliberately excludes, so this rule excludes them too. The Rust
 * rule's `additional_word_delimiters` option has no `Settings` field here,
 * so it is hardcoded to the Rust default (no additional delimiters).
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ValidHookNameRule extends CallRule
{
    private const HOOK_DEFINING_FUNCTIONS = [
        'do_action',
        'apply_filters',
        'do_action_ref_array',
        'apply_filters_ref_array',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/valid-hook-name',
            name: 'Valid hook name',
            description: 'Ensures that hook names defined via do_action() or apply_filters() follow WordPress naming conventions: lowercase letters, numbers, and underscores as word separators. Only the literal parts of a hook name are validated; dynamic parts of interpolated hook names are ignored.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return self::HOOK_DEFINING_FUNCTIONS;
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $position = Lists::HOOK_NAME_ARGUMENT_POSITION[$name] - 1;
        $value = $this->argument($context, $call, $position);
        if ($value === null) {
            return;
        }

        if ($value->kind === NodeKind::LiteralString) {
            $text = $this->unquote($context->file, $value);
            if ($text !== null) {
                $this->validate($context, $text, $value->span);
            }

            return;
        }

        if ($value->kind === NodeKind::CompositeString) {
            $this->checkComposite($context, $value);
        }
    }

    /**
     * Validates each literal part of an interpolated string. Dynamic parts
     * (variables, `{$expr}`) are skipped, matching the Rust rule.
     */
    private function checkComposite(LintContext $context, Node $composite): void
    {
        $file = $context->file;
        $string = $file->getChildren($composite)[0] ?? null;
        if ($string === null) {
            return;
        }

        $nowdoc = str_starts_with($file->getText($string), "<<<'");
        foreach ($file->getChildren($string) as $wrapper) {
            $part = $file->getChildren($wrapper)[0] ?? $wrapper;
            if ($part->kind !== NodeKind::LiteralStringPart) {
                continue;
            }

            $text = $file->getText($part);
            $this->validate($context, $nowdoc ? $text : $this->decodeEscapes($text), $part->span);
        }
    }

    /**
     * Returns the decoded value of a quoted literal string, or NULL for a
     * heredoc/nowdoc literal (untested by the Rust suite; its block
     * markers are not worth parsing for this rule).
     */
    private function unquote(SourceFile $file, Node $literal): ?string
    {
        $text = $file->getText($literal);
        $quote = $text[0] ?? '';
        $body = substr($text, offset: 1, length: -1);

        if ($quote === "'") {
            return strtr($body, ['\\\'' => '\'', '\\\\' => '\\']);
        }

        if ($quote === '"') {
            return $this->decodeEscapes($body);
        }

        return null;
    }

    /**
     * Decodes the double-quoted/heredoc escape sequences that the Rust
     * rule's lexer resolves before validation: control characters, octal
     * and hex byte escapes, and `\u{...}` Unicode code points.
     */
    private function decodeEscapes(string $text): string
    {
        $decoded = preg_replace_callback(
            '/\\\\(?:u\{([0-9A-Fa-f]+)\}|x([0-9A-Fa-f]{1,2})|([0-7]{1,3})|(.))/',
            static function (array $match): string {
                if ($match[1] !== '') {
                    return self::codepointToUtf8((int) hexdec($match[1]));
                }

                if ($match[2] !== '') {
                    return chr((int) hexdec($match[2]));
                }

                if ($match[3] !== '') {
                    return chr((int) octdec($match[3]) & 0xFF);
                }

                return match ($match[4]) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'v' => "\x0B",
                    'e' => "\x1B",
                    'f' => "\x0C",
                    '\\', '$', '"' => $match[4],
                    default => '\\' . $match[4],
                };
            },
            $text,
        );

        return $decoded ?? $text;
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0x80) {
            return chr($codepoint);
        }

        if ($codepoint < 0x800) {
            return chr(0xC0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3F));
        }

        if ($codepoint < 0x1_0000) {
            return (
                chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F))
            );
        }

        return (
            chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F))
        );
    }

    /**
     * Reports uppercase letters and non-underscore word separators in one
     * decoded hook-name fragment.
     */
    private function validate(LintContext $context, string $name, Span $span): void
    {
        $hasUppercase = false;
        /** @var list<string> $invalid */
        $invalid = [];

        $length = strlen($name);
        for ($index = 0; $index < $length; ++$index) {
            $byte = $name[$index];

            if ($byte >= 'A' && $byte <= 'Z') {
                $hasUppercase = true;
                continue;
            }

            if ($byte >= 'a' && $byte <= 'z' || $byte >= '0' && $byte <= '9' || $byte === '_') {
                continue;
            }

            // Non-ASCII bytes (e.g. UTF-8 letters) are not treated as delimiters.
            if (ord($byte) >= 0x80) {
                continue;
            }

            if (!in_array($byte, $invalid, strict: true)) {
                $invalid[] = $byte;
            }
        }

        if ($hasUppercase) {
            $context->report(Issue::new(
                'Hook names should be lowercase.',
                $span,
                'This hook name contains uppercase characters',
            )->withNote('WordPress hook names conventionally use only lowercase letters.')->withHelp(
                'Use lowercase letters in the hook name.',
            ));
        }

        if ($invalid !== []) {
            $characters = implode(', ', array_map(static fn(string $byte): string => "`{$byte}`", $invalid));

            $context->report(Issue::new(
                'Words in hook names should be separated by underscores.',
                $span,
                "This hook name uses {$characters} as a word separator",
            )->withNote('WordPress hook names conventionally use underscores between words.')->withHelp(
                'Replace the punctuation with underscores, or allow specific delimiters via the `additional-word-delimiters` option.',
            ));
        }
    }
}
