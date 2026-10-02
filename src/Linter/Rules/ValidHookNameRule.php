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
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;

/**
 * Ports `WordPress.NamingConventions.ValidHookName`.
 *
 * Skips the `*_deprecated` dispatchers in `Lists::HOOK_INVOKE_FUNCTIONS`, as
 * WPCS does: their first argument is the hook name, but the sniff only
 * checks the plain `do_action`/`apply_filters` family.
 */
final class ValidHookNameRule extends CallRule
{
    private const SNIFF = 'WordPress.NamingConventions.ValidHookName';

    private const SKIPPED = ['do_action_deprecated', 'apply_filters_deprecated'];

    /**
     * Wrapper and operator nodes whose string operands are part of the hook name.
     */
    private const TRAVERSED = [
        NodeKind::Expression,
        NodeKind::Literal,
        NodeKind::Binary,
        NodeKind::Parenthesized,
        NodeKind::Conditional,
    ];

    /**
     * Matches one byte that is not a valid hook-name character.
     */
    private readonly string $delimiterPattern;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $allowed = preg_quote($settings->additionalWordDelimiters, delimiter: '/');
        $this->delimiterPattern = "/[^A-Za-z0-9_\\x80-\\xFF{$allowed}]/";
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/valid-hook-name',
            name: 'Valid hook name',
            description: 'Ensures that hook names defined via do_action() or apply_filters() follow WordPress naming conventions: lowercase letters, numbers, and underscores as word separators. Only the literal parts of a hook name are validated; dynamic parts of interpolated hook names are ignored.',
            defaultLevel: Level::Error,
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

        $this->validate($context, implode('', $this->literalParts($context->file, $value)), $value->span);
    }

    /**
     * Collects the literal text of a hook-name expression, like WPCS: string
     * literals inside concatenations, parentheses and ternaries count, while
     * variables, array keys and call arguments are skipped. Dynamic parts of
     * interpolated strings are skipped too.
     *
     * @return list<string>
     */
    private function literalParts(SourceFile $file, Node $node): array
    {
        $text = Values::literalString($file, $node);
        if ($text !== null) {
            return [$text];
        }

        if ($node->kind === NodeKind::CompositeString) {
            $parts = [];
            foreach (Strings::compositeParts($file, $node) as $partText) {
                if ($partText === null) {
                    continue;
                }

                $parts[] = $partText;
            }

            return $parts;
        }

        if (!in_array($node->kind, self::TRAVERSED, strict: true)) {
            return [];
        }

        $parts = [];
        foreach ($file->getChildren($node) as $child) {
            $parts = [...$parts, ...$this->literalParts($file, $child)];
        }

        return $parts;
    }

    /**
     * Reports uppercase letters and non-underscore word separators in the
     * decoded literal hook-name fragments, once per problem, as WPCS does. Non-ASCII bytes (e.g. UTF-8 letters) are
     * not treated as delimiters.
     */
    private function validate(LintContext $context, string $name, Span $span): void
    {
        if (preg_match('/[A-Z]/', $name) === 1) {
            $this->report->issue(
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

            $this->report->issue(
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
