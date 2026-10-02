<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use DOMDocument;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
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
use function preg_replace;
use function preg_replace_callback;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function substr_count;
use function trim;
use function ucfirst;

use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;
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
     * Functions reserved for the low-level API.
     */
    private const LOW_LEVEL = ['translate', 'translate_with_gettext_context'];

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
        private readonly Report $report,
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
            defaultLevel: Level::Error,
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
        return [...array_keys(Lists::I18N_FUNCTIONS), '_'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $resolved = $context->getResolvedName();
        $written = $resolved !== null && $resolved->imported ? $resolved->name : $call->getName($context->file);
        if ($written === null || Calls::normalize($written) !== $name) {
            return;
        }

        if ($this->checkFunction($context, $call, $name)) {
            return;
        }

        if (Calls::isUnpacked($call)) {
            $this->reportUnpacked($context, $call, $name);

            return;
        }

        $parameters = self::PARAMETERS[Lists::I18N_FUNCTIONS[$name]];
        $arguments = [];
        foreach ($parameters as $index => $parameter) {
            $arguments[$parameter] = $this->argument($context, $call, $index, $parameter);
        }

        $texts = [];
        foreach (self::TEXT_PARAMETERS as $parameter) {
            if (!array_key_exists($parameter, $arguments)) {
                continue;
            }

            $argument = $arguments[$parameter];
            if ($argument === null) {
                $this->reportMissing($context, $name, $parameter);
                continue;
            }

            $text = $this->literal($context, $argument);
            $texts[] = [$argument, $text];
            if ($text !== null) {
                $this->checkOrder($context, $argument, $parameter, $text);
                $this->checkContent($context, $argument, $text);
                continue;
            }

            $this->reportNotLiteral($context, $name, $parameter, $argument->span);
        }

        $gettextContext = $arguments['context'] ?? null;
        if ($gettextContext === null && array_key_exists('context', $arguments)) {
            $this->reportMissing($context, $name, 'context');
        }

        if ($gettextContext !== null && $this->literal($context, $gettextContext) === null) {
            $this->report->issue(
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

        $this->checkDomain($context, $call, $name, $arguments['domain']);
        $this->checkTranslatorsComment($context, $name, $texts);

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
            $this->report->issue(
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
            $this->report->issue(
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

    private function checkDomain(LintContext $context, CallExpression $call, string $name, ?Node $argument): void
    {
        $allowed = $this->settings->textDomains;
        // WordPress core's own `default` domain, per WPCS's `$text_domain_is_default`/`$text_domain_contains_default`.
        $isDefaultOnly = $allowed === ['default'];

        if ($argument === null) {
            if ($allowed === [] || $isDefaultOnly) {
                // With no known text domain WPCS presumes WordPress core; `default` alone may be omitted.
                return;
            }

            $containsDefault = in_array('default', $allowed, strict: true);
            $this->report->issue(
                $context,
                Issue::new(
                    'Missing text domain in translation function call',
                    $context->node->span,
                    sprintf('This call to `%s()` does not pass a text domain', $name),
                )->withNote(
                    $containsDefault
                        ? 'If this text string is supposed to use the WordPress core translation, pass `default` as the text domain explicitly.'
                        : 'Without a text domain, WordPress falls back to the `default` (core) domain and the string will not be translated with your plugin or theme.',
                )->withHelp(
                    $containsDefault
                        ? "Pass your plugin or theme text domain as the last argument, e.g. `'my-plugin'`, or `'default'` for a WordPress core string."
                        : "Pass your plugin or theme text domain as the last argument, e.g. `'my-plugin'`.",
                ),
                $containsDefault
                    ? [self::SNIFF . '.MissingArgDomainDefault']
                    : [self::SNIFF . '.MissingArgDomain', self::SNIFF . '.MissingArgDomainDefault'],
            );

            return;
        }

        $domain = $this->literal($context, $argument);
        if ($domain === null) {
            $this->report->issue(
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

        if ($allowed === [] && $domain === '') {
            $this->report->issue(
                $context,
                Issue::new(
                    'Empty text domain in translation function call',
                    $argument->span,
                    'This text domain is empty',
                )->withHelp('Pass a text domain or remove the argument.'),
                [self::SNIFF . '.EmptyTextDomain'],
            );

            return;
        }

        if ($isDefaultOnly && $domain === 'default') {
            $issue = Issue::new(
                'Superfluous `default` text domain in translation function call',
                $argument->span,
                sprintf(
                    'No need to supply the text domain in a call to `%s()` when `default` is the only accepted text domain',
                    $name,
                ),
            )->withHelp('Remove the text domain argument.');
            $removal = self::domainRemoval($context->file, $call, $argument);
            if ($removal !== null) {
                $issue = $issue->withEdit(TextEdit::delete($removal));
            }

            $this->report->issue($context, $issue, [self::SNIFF . '.SuperfluousDefaultTextDomain']);

            return;
        }

        if ($allowed === [] || in_array($domain, $allowed, strict: true)) {
            return;
        }

        $this->report->issue(
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
     * What the sniff's fixer removes for a superfluous `default` domain: from the comma before the argument
     * through the string, or, when it is the first (named) argument, from after `(` through the comma after it.
     * Null when that would drop a comment or the argument is not a plain string literal.
     */
    private static function domainRemoval(SourceFile $file, CallExpression $call, Node $value): ?Span
    {
        if ($value->kind !== NodeKind::LiteralString) {
            return null;
        }

        foreach ($call->arguments as $index => $argument) {
            if (
                $argument->value->span->start > $value->span->start
                || $argument->value->span->end < $value->span->end
            ) {
                continue;
            }

            $argumentSpan = $argument->node->span;
            if ($index > 0) {
                $comma = self::separator($file, $call->arguments[$index - 1]->node->span->end, $argumentSpan->start);
                $span = $comma === null ? null : new Span($comma, $value->span->end);
            } else {
                $list = $file->getParent($argument->node);
                $next = $call->arguments[1] ?? null;
                $comma = $next === null ? null : self::separator($file, $argumentSpan->end, $next->node->span->start);
                $end = $comma === null ? ($list?->span->end ?? 1) - 1 : $comma + 1;
                $span = $list === null ? null : new Span($list->span->start + 1, $end);
            }

            return $span === null || Calls::hasComment($file, $span) ? null : $span;
        }

        return null;
    }

    /**
     * The offset of the argument-separating comma between two arguments, skipping commas inside comments.
     */
    private static function separator(SourceFile $file, int $start, int $end): ?int
    {
        $text = $file->getText(new Span($start, $end));
        $offset = 0;
        while (($comma = strpos($text, needle: ',', offset: $offset)) !== false) {
            if (!Calls::hasComment($file, new Span($start + $comma, $start + $comma + 1))) {
                return $start + $comma;
            }

            $offset = $comma + 1;
        }

        return null;
    }

    /**
     * Reports `_()`, the low-level functions and extra arguments; true when `_()` ends the check.
     */
    private function checkFunction(LintContext $context, CallExpression $call, string $name): bool
    {
        if ($name === '_') {
            $this->report->issue(
                $context,
                Issue::new(
                    'Single-underscore `_()` gettext function',
                    $context->node->span,
                    'Found `_()` where `__()` is expected',
                )->withHelp('Use `__()` with a text domain.'),
                [self::SNIFF . '.SingleUnderscoreGetTextFunction'],
            );

            return true;
        }

        if (in_array($name, self::LOW_LEVEL, strict: true)) {
            $this->report->issue(
                $context,
                Issue::new(
                    'Low-level translation function',
                    $context->node->span,
                    sprintf('`%s()` is reserved for low-level API usage', $name),
                )->withHelp('Use `__()`, `_x()` or one of their escaping variants instead.'),
                [self::SNIFF . '.LowLevelTranslationFunction'],
            );
        }

        $parameters = self::PARAMETERS[Lists::I18N_FUNCTIONS[$name]];
        if (count($call->arguments) > count($parameters)) {
            $this->report->issue(
                $context,
                Issue::new(
                    'Too many arguments in translation function call',
                    $context->node->span,
                    sprintf(
                        '`%s()` expects %d arguments, received %d',
                        $name,
                        count($parameters),
                        count($call->arguments),
                    ),
                )->withHelp('Remove the extra arguments.'),
                [self::SNIFF . '.TooManyFunctionArgs'],
            );
        }

        return false;
    }

    private function reportMissing(LintContext $context, string $name, string $parameter): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                sprintf('Missing `$%s` argument in translation function call', $parameter),
                $context->node->span,
                sprintf('This call to `%s()` does not pass `$%s`', $name, $parameter),
            ),
            [self::SNIFF . '.MissingArg' . ucfirst($parameter)],
        );
    }

    /**
     * Reports a string with nothing but placeholders, or wrapped entirely in one HTML element.
     */
    private function checkContent(LintContext $context, Node $argument, string $text): void
    {
        $text = trim($text);
        if (preg_replace(self::WPCS_PLACEHOLDER, replacement: '', subject: $text) === '') {
            $this->report->issue(
                $context,
                Issue::new(
                    'Translatable string has no translatable content',
                    $argument->span,
                    'Only placeholders here',
                )->withHelp('Move the formatting out of the translation, or add the text translators need.'),
                [self::SNIFF . '.NoEmptyStrings'],
            );

            return;
        }

        if (!self::isHtmlWrapped($text)) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Translatable string should not be wrapped in HTML',
                $argument->span,
                'The whole string is one element',
            )->withHelp('Move the wrapping element out of the translatable string.'),
            [self::SNIFF . '.NoHtmlWrappedStrings'],
        );
    }

    /**
     * WPCS's XMLReader test: the string is one well-formed element, without placeholders in its attributes
     * and not a link with an `href`.
     */
    private static function isHtmlWrapped(string $text): bool
    {
        // Plain text cannot be one element; skip the XML parse for it.
        if (!str_starts_with($text, '<') || !str_ends_with($text, '>')) {
            return false;
        }

        $document = new DOMDocument();
        if (!$document->loadXML($text, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return false;
        }

        $element = $document->documentElement;
        if ($element === null || $document->firstChild !== $element) {
            return false;
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if (preg_match(self::WPCS_PLACEHOLDER, $attribute->value) === 1) {
                return false;
            }
        }

        if ($element->nodeName === 'a' && $element->getAttribute('href') !== '') {
            return false;
        }

        return $document->saveXML($element) === $text;
    }

    /**
     * Reports a string with several placeholders that are not all numbered.
     */
    private function checkOrder(LintContext $context, Node $argument, string $parameter, string $text): void
    {
        $unordered = [];
        $unorderedCount = (int) preg_match_all(self::WPCS_UNORDERED_PLACEHOLDER, $text, $unordered);
        $all = [];
        $allCount = (int) preg_match_all(self::WPCS_PLACEHOLDER, $text, $all);

        if ($unorderedCount > 0 && $unorderedCount !== $allCount && $allCount > 1) {
            $this->report->issue(
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

        $issue = Issue::new(
            'Multiple placeholders in translatable strings should be ordered',
            $argument->span,
            sprintf('Found %s', implode(', ', $found)),
        )->withHelp(sprintf('Number the placeholders so translators can reorder them: %s.', implode(', ', $expected)));

        // Fixable, as in the sniff: number each unordered placeholder in turn. It changes the msgid, so existing
        // translations of the string stop matching. Positions come from the detection regex, so a literal `%%s`
        // is never numbered (the sniff's fixer numbers it).
        $raw = $context->file->getText($argument);
        $dollar = str_starts_with($raw, '"') ? '\$' : '$';
        $number = 0;
        $numbered = 0;
        $fixed = (string) preg_replace_callback(
            self::WPCS_UNORDERED_PLACEHOLDER,
            static function (array $match) use (&$number, $dollar): string {
                return '%' . ++$number . $dollar . substr($match[0], offset: 1);
            },
            $raw,
            count: $numbered,
        );
        if ($argument->kind === NodeKind::LiteralString && $numbered === count($found)) {
            $edit = TextEdit::replace($argument->span, $fixed);
            $issue = $issue->withEdit($edit->withSafety(Safety::PotentiallyUnsafe));
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.UnorderedPlaceholders' . ucfirst($parameter)]);
    }

    /**
     * Requires a `translators:` comment before a call whose literal text has a placeholder, placed as WPCS expects:
     * the last comment before the call ends on the line above it, or only whitespace or same-line code separates them.
     *
     * @param list<array{Node, ?string}> $texts
     */
    private function checkTranslatorsComment(LintContext $context, string $name, array $texts): void
    {
        $needsComment = false;
        // WPCS matches the source text, so `%1\$s` in a double-quoted string is no placeholder.
        foreach ($texts as [$argument, $text]) {
            $needsComment =
                $needsComment
                || $text !== null && preg_match(self::WPCS_PLACEHOLDER, $context->file->getText($argument)) === 1;
        }

        if (!$needsComment) {
            return;
        }

        $style = self::translatorsCommentBefore($context->file, $context->node->span->start);
        if ($style === TriviaKind::DocBlockComment) {
            $this->report->issue(
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

        $this->report->issue(
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
        $trivia = $file->getTrivia();
        $low = 0;
        $high = count($trivia);
        while ($low < $high) {
            $mid = ($low + $high) >> 1;
            if ($trivia[$mid]->span->end > $start) {
                $high = $mid;
                continue;
            }

            $low = $mid + 1;
        }

        $comment = $trivia[$low - 1] ?? null;
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
     * A spread argument (`_n( ...$args )`): WPCS reads it as the parameter at its position and
     * reports it as not a literal.
     */
    private function reportUnpacked(LintContext $context, CallExpression $call, string $name): void
    {
        $parameters = self::PARAMETERS[Lists::I18N_FUNCTIONS[$name]];
        foreach ($call->arguments as $index => $argument) {
            $parameter = $parameters[$index] ?? null;
            if ($parameter === null) {
                return;
            }

            if ($argument->unpacked && $argument->name === null) {
                $this->report->issue(
                    $context,
                    Issue::new(
                        'Translatable text must be a literal string',
                        $argument->node->span,
                        sprintf('This argument to `%s()` is a spread, not a literal string', $name),
                    ),
                    [self::SNIFF . '.NonSingularStringLiteral' . ucfirst($parameter)],
                );

                return;
            }

            if (
                $argument->name === null
                && in_array($parameter, self::TEXT_PARAMETERS, strict: true)
                && $this->literal($context, $argument->node) === null
            ) {
                $this->reportNotLiteral($context, $name, $parameter, $argument->node->span);
            }
        }
    }

    private function reportNotLiteral(LintContext $context, string $name, string $parameter, Span $span): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                'Translatable text must be a literal string',
                $span,
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
