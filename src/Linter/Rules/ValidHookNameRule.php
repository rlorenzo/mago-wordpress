<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Strings;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_diff;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;

/**
 * Ports `WordPress.NamingConventions.ValidHookName`.
 *
 * Only checks the hook-defining calls the Rust spec covers: it skips the
 * `*_deprecated` dispatchers in `Lists::HOOK_INVOKE_FUNCTIONS`, as the Rust
 * rule does.
 */
final class ValidHookNameRule extends CallRule
{
    private const SNIFF = 'WordPress.NamingConventions.ValidHookName';

    private const SKIPPED = ['do_action_deprecated', 'apply_filters_deprecated'];

    /**
     * Matches one byte that is not a valid hook-name character.
     */
    private readonly string $delimiterPattern;

    public function __construct(Settings $settings)
    {
        $allowed = preg_quote($settings->additionalWordDelimiters, delimiter: '/');
        $this->delimiterPattern = "/[^A-Za-z0-9_\\x80-\\xFF{$allowed}]/";
    }

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
        return array_values(array_diff(Lists::HOOK_INVOKE_FUNCTIONS, self::SKIPPED));
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $position = Lists::HOOK_NAME_ARGUMENT_POSITION[$name] - 1;
        $value = $this->argument($context, $call, $position, 'hook_name');
        if ($value === null) {
            return;
        }

        $text = Values::literalString($context->file, $value);
        if ($text !== null) {
            $this->validate($context, $text, $value->span);

            return;
        }

        if ($value->kind !== NodeKind::CompositeString) {
            return;
        }

        // Dynamic parts (variables, `{$expr}`) are skipped, matching the Rust rule.
        foreach (Strings::compositeParts($context->file, $value) as $part => $partText) {
            if ($partText === null) {
                continue;
            }

            $this->validate($context, $partText, $part->span);
        }
    }

    /**
     * Reports uppercase letters and non-underscore word separators in one
     * decoded hook-name fragment. Non-ASCII bytes (e.g. UTF-8 letters) are
     * not treated as delimiters.
     */
    private function validate(LintContext $context, string $name, Span $span): void
    {
        if (preg_match('/[A-Z]/', $name) === 1) {
            Report::issue(
                $context,
                Issue::new(
                    'Hook names should be lowercase.',
                    $span,
                    'This hook name contains uppercase characters',
                )->withNote('WordPress hook names conventionally use only lowercase letters.')->withHelp(
                    'Use lowercase letters in the hook name.',
                ),
                [self::SNIFF . '.NotLowercase'],
            );
        }

        $matches = [];
        preg_match_all($this->delimiterPattern, $name, $matches);
        if (($matches[0] ?? []) !== []) {
            $characters = implode(', ', array_map(
                static fn(string $byte): string => "`{$byte}`",
                array_unique($matches[0]),
            ));

            Report::issue(
                $context,
                Issue::new(
                    'Words in hook names should be separated by underscores.',
                    $span,
                    "This hook name uses {$characters} as a word separator",
                )->withNote('WordPress hook names conventionally use underscores between words.')->withHelp(
                    'Replace the punctuation with underscores, or allow specific delimiters via the `additional-word-delimiters` option.',
                ),
                [self::SNIFF . '.UseUnderscores'],
            );
        }
    }
}
