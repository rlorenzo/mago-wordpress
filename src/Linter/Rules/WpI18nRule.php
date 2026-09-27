<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_count_values;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function in_array;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * Ports `WordPress.WP.I18n`.
 *
 * The function-like targets exist only to give calls an ancestor chain, at the cost of shipping every function body.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class WpI18nRule extends CallRule
{
    /**
     * Argument positions per Lists::I18N_FUNCTIONS kind: text arguments, context argument, domain argument.
     */
    private const SHAPES = [
        'simple' => [[0], null, 1],
        'context' => [[0], 1, 2],
        'number' => [[0, 1], null, 3],
        'number_context' => [[0, 1], 3, 4],
        'noopnumber' => [[0, 1], null, 2],
        'noopnumber_context' => [[0, 1], 2, 3],
    ];

    /**
     * WordPress's parameter names per Lists::I18N_FUNCTIONS kind, in position order, for binding named arguments.
     */
    private const PARAMETERS = [
        'simple' => ['text', 'domain'],
        'context' => ['text', 'context', 'domain'],
        'number' => ['single', 'plural', 'number', 'domain'],
        'number_context' => ['single', 'plural', 'number', 'context', 'domain'],
        'noopnumber' => ['singular', 'plural', 'domain'],
        'noopnumber_context' => ['singular', 'plural', 'context', 'domain'],
    ];

    /**
     * WPCS lists this one, but the ported rule does not check it.
     */
    private const SKIPPED = 'translate_with_gettext_context';

    /**
     * printf-style placeholders. The space flag is deliberately absent, so `100% off` is not a `% o`.
     */
    private const PLACEHOLDER = "/%(?:%|(?:\\d+\\$)?(?:[-+0]|'.)*+\\d*+(?:\\.\\d*+)?+[bcdeEfFgGhHosuxX])/s";

    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/wp-i18n',
            name: 'WordPress I18n',
            description: 'Validates calls to the WordPress translation functions (`__`, `_e`, `_x`, `_n`, `esc_html__`, and friends). '
            . 'Translatable text and gettext context arguments must be literal strings so that translation tools such as '
            . "`xgettext` and WP-CLI's `i18n make-pot` can extract them. Every call must also pass a literal text domain, "
            . 'and for the plural functions the singular and plural strings should use consistent printf-style placeholders. '
            . 'The `text-domains` setting can list the allowed text domains; when non-empty, any other literal text domain is reported.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // The function-like targets put the enclosing declarations into the snapshot, so a call has ancestors.
            targets: [
                NodeKind::FunctionCall,
                NodeKind::Function,
                NodeKind::Method,
                NodeKind::Closure,
                NodeKind::ArrowFunction,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind === NodeKind::FunctionCall) {
            parent::lint($context);
        }
    }

    protected function names(): array
    {
        return array_values(array_diff(array_keys(Lists::I18N_FUNCTIONS), [self::SKIPPED]));
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $resolved = $context->getResolvedName();
        $written = $resolved !== null && $resolved->imported ? $resolved->name : $call->getName($context->file);
        if ($written === null || Calls::normalize($written) !== $name || $this->insideWrapper($context)) {
            return;
        }

        $shape = Lists::I18N_FUNCTIONS[$name];
        $arguments = [];
        foreach ($call->arguments as $argument) {
            if ($argument->unpacked) {
                return;
            }

            $index = $argument->name === null
                ? count($arguments)
                : array_search($argument->name, self::PARAMETERS[$shape], strict: true);
            if ($index !== false) {
                $arguments[$index] = $argument->value;
            }
        }

        [$textIndexes, $contextIndex, $domainIndex] = self::SHAPES[$shape];

        $texts = [];
        foreach ($textIndexes as $index) {
            if (!array_key_exists($index, $arguments)) {
                continue;
            }

            $text = $this->literal($context, $arguments[$index]);
            $texts[] = [$arguments[$index], $text];
            if ($text === null) {
                $context->report(Issue::new(
                    'Translatable text must be a literal string',
                    $arguments[$index]->span,
                    sprintf('This argument to `%s()` is not a literal string', $name),
                )->withNote(
                    'Translation tools statically extract translatable strings from the source code; variables, concatenations, and interpolations cannot be extracted.',
                )->withHelp(
                    'Pass a single-quoted or double-quoted literal string without variables, and use `sprintf()` for dynamic values.',
                ));
            }
        }

        if (
            $contextIndex !== null
            && array_key_exists($contextIndex, $arguments)
            && $this->literal($context, $arguments[$contextIndex]) === null
        ) {
            $context->report(Issue::new(
                'Translation context must be a literal string',
                $arguments[$contextIndex]->span,
                sprintf('The context argument to `%s()` is not a literal string', $name),
            )->withNote(
                'The gettext context is extracted statically by translation tools and must be a literal string.',
            )->withHelp("Pass the context as a literal string, e.g. `'noun'`."));
        }

        $this->checkDomain($context, $name, $arguments[$domainIndex] ?? null);

        if (count($texts) !== 2) {
            return;
        }

        [
            [$singularNode, $singular],
            [$pluralNode,   $plural],
        ] = $texts;
        if (
            $singular !== null
            && $plural !== null
            && !self::compatible(self::placeholders($singular), self::placeholders($plural))
        ) {
            $context->report(
                Issue::new(
                    'Mismatched placeholders between singular and plural strings',
                    $singularNode->span,
                    'The singular string uses different placeholders',
                )
                    ->withSecondaryAnnotation($pluralNode->span, '...than the plural string')
                    ->withNote(
                        'Singular and plural strings are formatted with the same arguments, so their printf-style placeholders must be compatible.',
                    )
                    ->withHelp(
                        'Use the same placeholders in both strings, preferring numbered placeholders such as `%1$s` when there is more than one.',
                    ),
            );
        }
    }

    private function checkDomain(LintContext $context, string $name, ?Node $argument): void
    {
        if ($argument === null) {
            $context->report(Issue::new(
                'Missing text domain in translation function call',
                $context->node->span,
                sprintf('This call to `%s()` does not pass a text domain', $name),
            )->withNote(
                'Without a text domain, WordPress falls back to the `default` (core) domain and the string will not be translated with your plugin or theme.',
            )->withHelp("Pass your plugin or theme text domain as the last argument, e.g. `'my-plugin'`."));

            return;
        }

        $domain = $this->literal($context, $argument);
        if ($domain === null) {
            $context->report(Issue::new(
                'Text domain must be a literal string',
                $argument->span,
                sprintf('The text domain argument to `%s()` is not a literal string', $name),
            )->withNote(
                'Translation tools match strings to a text domain statically; a dynamic text domain cannot be resolved.',
            )->withHelp("Pass the text domain as a literal string, e.g. `'my-plugin'`."));

            return;
        }

        $allowed = $this->settings->textDomains;
        if ($allowed === [] || in_array($domain, $allowed, strict: true)) {
            return;
        }

        $context->report(Issue::new(
            'Unexpected text domain in translation function call',
            $argument->span,
            sprintf('The text domain `%s` is not in the configured list', $domain),
        )->withNote(
            'The `text-domains` setting restricts which text domains may be used in this project.',
        )->withHelp(sprintf('Use one of the configured text domains: %s.', implode(', ', array_map(
            static fn(string $domain): string => "`{$domain}`",
            $allowed,
        )))));
    }

    private function literal(LintContext $context, Node $node): ?string
    {
        return Values::literalString($context->file, Values::unwrap($context->file, $node));
    }

    /**
     * Whether the call sits directly inside a function or method that is itself named like a translation function.
     */
    private function insideWrapper(LintContext $context): bool
    {
        foreach ($context->file->getAncestors($context->node) as $ancestor) {
            if ($ancestor->kind === NodeKind::Closure || $ancestor->kind === NodeKind::ArrowFunction) {
                return false;
            }

            if ($ancestor->kind !== NodeKind::Function && $ancestor->kind !== NodeKind::Method) {
                continue;
            }

            foreach ($context->file->getChildren($ancestor) as $child) {
                if ($child->kind !== NodeKind::LocalIdentifier) {
                    continue;
                }

                $declared = strtolower($context->file->getText($child));

                return $declared !== self::SKIPPED && array_key_exists($declared, Lists::I18N_FUNCTIONS);
            }

            return false;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $text): array
    {
        $matches = [];
        preg_match_all(self::PLACEHOLDER, $text, $matches);

        return array_values(array_diff($matches[0], ['%%']));
    }

    /**
     * The singular may omit at most one placeholder (the count). Numbered placeholders may be reordered;
     * unnumbered ones are consumed in order, so the singular ones must be a prefix of the plural ones.
     *
     * @param list<string> $singular
     * @param list<string> $plural
     */
    private static function compatible(array $singular, array $plural): bool
    {
        if (count($plural) !== count($singular) && count($plural) !== (count($singular) + 1)) {
            return false;
        }

        foreach ([...$singular, ...$plural] as $placeholder) {
            if (!str_contains($placeholder, '$')) {
                return array_slice($plural, offset: 0, length: count($singular)) === $singular;
            }
        }

        $available = array_count_values($plural);
        foreach (array_count_values($singular) as $placeholder => $count) {
            if ($count > ($available[$placeholder] ?? 0)) {
                return false;
            }
        }

        return true;
    }
}
