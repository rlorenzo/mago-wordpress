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
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function sort;
use function sprintf;
use function str_contains;
use function strtolower;
use function substr;
use function substr_count;
use function trim;
use function ucfirst;

use const SORT_NATURAL;

/**
 * Ports `WordPress.WP.I18n`.
 *
 * The function-like targets exist only to give calls an ancestor chain, at the cost of shipping every function body.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class WpI18nRule extends CallRule
{
    private const SNIFF = 'WordPress.WP.I18n';

    /**
     * WordPress's parameter names per Lists::I18N_FUNCTIONS kind, in position order.
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
     * The translatable text parameters, singular before plural.
     */
    private const TEXT_PARAMETERS = ['text', 'single', 'singular', 'plural'];

    /**
     * WPCS lists this one, but the ported rule does not check it.
     */
    private const SKIPPED = 'translate_with_gettext_context';

    /**
     * WPCS's `SPRINTF_PLACEHOLDER_REGEX`, which finds the placeholders for the translators comment and the singular/plural comparison.
     */
    private const WPCS_PLACEHOLDER = '/(?<!%)%(?:\d+\$)?[+-]?(?:(?:0|\'.)?-?\d*(?:\.(?:[ 0]|\'.)?\d+)?|[ ]?-?\d+(?:\.(?:[ 0]|\'.)?\d+)?)[bcdeEfFgGhHosuxX]/';

    /**
     * WPCS's `UNORDERED_SPRINTF_PLACEHOLDER_REGEX`: a placeholder without a position specifier.
     */
    private const WPCS_UNORDERED_PLACEHOLDER = '/(?<!%)%[+-]?(?:(?:0|\'.)?-?\d*(?:\.(?:[ 0]|\'.)?\d+)?|[ ]?-?\d+(?:\.(?:[ 0]|\'.)?\d+)?)[bcdeEfFgGhHosuxX]/';

    private const TRANSLATORS_COMMENT = '`^(?:(?://|/\*{1,2}) )?translators:`i';

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

        if (Calls::isUnpacked($call)) {
            return;
        }

        $arguments = [];
        foreach (self::PARAMETERS[Lists::I18N_FUNCTIONS[$name]] as $index => $parameter) {
            $arguments[$parameter] = $this->argument($context, $call, $index, $parameter);
        }

        $texts = [];
        foreach (self::TEXT_PARAMETERS as $parameter) {
            $argument = $arguments[$parameter] ?? null;
            if ($argument === null) {
                continue;
            }

            $text = $this->literal($context, $argument);
            $texts[] = [$argument, $text];
            if ($text !== null) {
                self::checkOrder($context, $argument, $parameter, $text);
                continue;
            }

            Report::issue(
                $context,
                Issue::new(
                    'Translatable text must be a literal string',
                    $argument->span,
                    sprintf('This argument to `%s()` is not a literal string', $name),
                )->withNote(
                    'Translation tools statically extract translatable strings from the source code; variables, concatenations, and interpolations cannot be extracted.',
                )->withHelp(
                    'Pass a single-quoted or double-quoted literal string without variables, and use `sprintf()` for dynamic values.',
                ),
                [
                    self::SNIFF . '.NonSingularStringLiteral' . ucfirst($parameter),
                    self::SNIFF . '.InterpolatedVariable' . ucfirst($parameter),
                ],
            );
        }

        $gettextContext = $arguments['context'] ?? null;
        if ($gettextContext !== null && $this->literal($context, $gettextContext) === null) {
            Report::issue(
                $context,
                Issue::new(
                    'Translation context must be a literal string',
                    $gettextContext->span,
                    sprintf('The context argument to `%s()` is not a literal string', $name),
                )->withNote(
                    'The gettext context is extracted statically by translation tools and must be a literal string.',
                )->withHelp("Pass the context as a literal string, e.g. `'noun'`."),
                [self::SNIFF . '.NonSingularStringLiteralContext', self::SNIFF . '.InterpolatedVariableContext'],
            );
        }

        $this->checkDomain($context, $name, $arguments['domain']);
        self::checkTranslatorsComment($context, $name, $texts);

        if (count($texts) !== 2) {
            return;
        }

        [
            [$singularNode, $singular],
            [$pluralNode,   $plural],
        ] = $texts;
        if ($singular === null || $plural === null) {
            return;
        }

        $singularPlaceholders = self::placeholders($singular);
        $pluralPlaceholders = self::placeholders($plural);

        // English conflates "singular" with "only one", but some languages use the
        // singular form for other counts too, so it needs the placeholders as well.
        if (count($singularPlaceholders) < count($pluralPlaceholders)) {
            Report::issue(
                $context,
                Issue::new(
                    'Missing singular placeholder, needed for some languages',
                    $singularNode->span,
                    'The singular string has fewer placeholders',
                )
                    ->withSecondaryAnnotation($pluralNode->span, '...than the plural string')
                    ->withNote(
                        'Some languages use the singular form for counts other than one, so it must show the number too.',
                    )
                    ->withHelp('Use the same placeholders in the singular string as in the plural string.'),
                [self::SNIFF . '.MissingSingularPlaceholder'],
            );

            return;
        }

        // Reordering is fine, but mismatched placeholders are probably wrong.
        sort($singularPlaceholders, SORT_NATURAL);
        sort($pluralPlaceholders, SORT_NATURAL);
        if ($singularPlaceholders !== $pluralPlaceholders) {
            Report::issue(
                $context,
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
                [self::SNIFF . '.MismatchedPlaceholders'],
            );
        }
    }

    private function checkDomain(LintContext $context, string $name, ?Node $argument): void
    {
        if ($argument === null) {
            Report::issue(
                $context,
                Issue::new(
                    'Missing text domain in translation function call',
                    $context->node->span,
                    sprintf('This call to `%s()` does not pass a text domain', $name),
                )->withNote(
                    'Without a text domain, WordPress falls back to the `default` (core) domain and the string will not be translated with your plugin or theme.',
                )->withHelp("Pass your plugin or theme text domain as the last argument, e.g. `'my-plugin'`."),
                [self::SNIFF . '.MissingArgDomain', self::SNIFF . '.MissingArgDomainDefault'],
            );

            return;
        }

        $domain = $this->literal($context, $argument);
        if ($domain === null) {
            Report::issue(
                $context,
                Issue::new(
                    'Text domain must be a literal string',
                    $argument->span,
                    sprintf('The text domain argument to `%s()` is not a literal string', $name),
                )->withNote(
                    'Translation tools match strings to a text domain statically; a dynamic text domain cannot be resolved.',
                )->withHelp("Pass the text domain as a literal string, e.g. `'my-plugin'`."),
                [self::SNIFF . '.NonSingularStringLiteralDomain', self::SNIFF . '.InterpolatedVariableDomain'],
            );

            return;
        }

        $allowed = $this->settings->textDomains;
        if ($allowed === [] || in_array($domain, $allowed, strict: true)) {
            return;
        }

        Report::issue(
            $context,
            Issue::new(
                'Unexpected text domain in translation function call',
                $argument->span,
                sprintf('The text domain `%s` is not in the configured list', $domain),
            )->withNote(
                'The `text-domains` setting restricts which text domains may be used in this project.',
            )->withHelp(sprintf('Use one of the configured text domains: %s.', implode(', ', array_map(
                static fn(string $domain): string => "`{$domain}`",
                $allowed,
            )))),
            [self::SNIFF . '.TextDomainMismatch'],
        );
    }

    /**
     * Reports a string with several placeholders that are not all numbered.
     */
    private static function checkOrder(LintContext $context, Node $argument, string $parameter, string $text): void
    {
        $unordered = [];
        $unorderedCount = (int) preg_match_all(self::WPCS_UNORDERED_PLACEHOLDER, $text, $unordered);
        $all = [];
        $allCount = (int) preg_match_all(self::WPCS_PLACEHOLDER, $text, $all);

        if ($unorderedCount > 0 && $unorderedCount !== $allCount && $allCount > 1) {
            Report::issue(
                $context,
                Issue::new(
                    'Mix of ordered and unordered placeholders in translatable string',
                    $argument->span,
                    sprintf('Found %s', implode(', ', $all[0])),
                )->withHelp('Number every placeholder, e.g. `%1$s` and `%2$s`, so translators can reorder them.'),
                [self::SNIFF . '.MixedOrderedPlaceholders' . ucfirst($parameter)],
            );

            return;
        }

        if ($unorderedCount < 2) {
            return;
        }

        $expected = [];
        /** @var list<string> $found */
        $found = $unordered[0];
        foreach ($found as $index => $placeholder) {
            $expected[] = '%' . ($index + 1) . '$' . substr($placeholder, offset: 1);
        }

        Report::issue(
            $context,
            Issue::new(
                'Multiple placeholders in translatable strings should be ordered',
                $argument->span,
                sprintf('Found %s', implode(', ', $found)),
            )->withHelp(sprintf('Number the placeholders so translators can reorder them: %s.', implode(
                ', ',
                $expected,
            ))),
            [self::SNIFF . '.UnorderedPlaceholders' . ucfirst($parameter)],
        );
    }

    /**
     * Requires a `translators:` comment before a call whose literal text has a placeholder, placed as WPCS expects:
     * the last comment before the call ends on the line above it, or only whitespace or same-line code separates them.
     *
     * @param list<array{Node, ?string}> $texts
     */
    private static function checkTranslatorsComment(LintContext $context, string $name, array $texts): void
    {
        $needsComment = false;
        foreach ($texts as [, $text]) {
            $needsComment = $needsComment || $text !== null && preg_match(self::WPCS_PLACEHOLDER, $text) === 1;
        }

        if (!$needsComment) {
            return;
        }

        $style = self::translatorsCommentBefore($context->file, $context->node->span->start);
        if ($style === TriviaKind::DocBlockComment) {
            Report::issue(
                $context,
                Issue::new(
                    'A translators comment must be a `/* */` style comment',
                    $context->node->span,
                    'Preceded by a docblock `translators:` comment',
                )->withNote('Tools that generate the .pot file do not pick up docblock comments.')->withHelp(
                    'Change `/**` to `/*`.',
                ),
                [self::SNIFF . '.TranslatorsCommentWrongStyle'],
            );

            return;
        }

        if ($style !== null) {
            return;
        }

        Report::issue(
            $context,
            Issue::new(
                'Missing translators comment for a string with placeholders',
                $context->node->span,
                sprintf('This call to `%s()` has placeholders but no `translators:` comment on the line above', $name),
            )->withHelp(
                'Add a `/* translators: %s: What the placeholder stands for. */` comment directly before the call.',
            ),
            [self::SNIFF . '.MissingTranslatorsComment'],
        );
    }

    /**
     * The kind of the `translators:` comment WPCS accepts for a call at $start, or NULL: the last comment
     * before the call must end on the line above it, or be followed only by whitespace or same-line code.
     * PHPCS reads a docblock from its first text line and joins the trimmed lines of any other comment.
     */
    private static function translatorsCommentBefore(SourceFile $file, int $start): ?TriviaKind
    {
        // Trivia come in source order, so the last one that ends before the call is the nearest.
        $comment = null;
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->span->end > $start) {
                break;
            }

            $comment = $trivia;
        }

        if ($comment === null) {
            return null;
        }

        $end = $comment->span->end;
        $between = substr($file->contents, $end, $start - $end);
        // Newlines from the comment's last character to the call: 1 when the comment ends on the line above.
        $lineGap = substr_count($file->contents, needle: "\n", offset: $end - 1, length: $start - $end + 1);
        if (str_contains(ltrim($between), needle: "\n") && $lineGap !== 1) {
            return null;
        }

        $source = $file->getText($comment->span);
        $text = implode('', array_map(trim(...), explode("\n", trim($source))));
        if ($comment->kind === TriviaKind::DocBlockComment) {
            foreach (explode("\n", substr($source, offset: 3, length: -2)) as $line) {
                $text = trim(ltrim(trim($line), characters: '*'));
                if ($text !== '') {
                    break;
                }
            }
        }

        return preg_match(self::TRANSLATORS_COMMENT, $text) === 1 ? $comment->kind : null;
    }

    private function literal(LintContext $context, Node $node): ?string
    {
        return Values::literalString($context->file, Values::unparenthesize($context->file, $node));
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
        preg_match_all(self::WPCS_PLACEHOLDER, $text, $matches);

        return array_values($matches[0]);
    }
}
