<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use Mago\Sdk\Syntax\CallArgument;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Strings;
use Rlorenzo\MagoWordPress\Internal\Values;

use function array_filter;
use function count;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function str_contains;
use function str_ends_with;
use function strtolower;

/**
 * Ports the `process_token()` walk of WPCS
 * `WordPress.DB.PreparedSQLPlaceholders` over the query of a
 * `$wpdb->prepare()` call.
 *
 * WPCS walks the query tokens in order, text strings nested in function
 * calls included, and skips only the arguments of a `sprintf()` after its
 * format and of an `implode()` that builds a valid `IN ()` list. This walks
 * the query nodes the same way; each string literal stands in for a token.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PreparedQuery
{
    /**
     * Stands in for an embed of an interpolated string.
     */
    private const GAP = "\0";

    /** @var list<array{string, Node, string}> */
    private array $findings = [];

    private bool $textFound = false;

    private bool $variableFound = false;

    private bool $wildcardFound = false;

    private int $placeholders = 0;

    private int $usesIn = 0;

    private int $implodeFill = 0;

    private int $adjustment = 0;

    private function __construct(
        private readonly SourceFile $file,
        private readonly bool $identifierSupported,
    ) {}

    /**
     * Analyzes a `prepare()` call on `$wpdb` or the global `wpdb` class.
     * $identifierSupported says whether the minimum WordPress version has
     * `%i`.
     *
     * @return list<array{string, Node, string}> Each finding's WPCS message
     *     code, the node it is on, and the text it found.
     */
    public static function analyze(SourceFile $file, CallExpression $call, bool $identifierSupported): array
    {
        if ($call->receiver === null || !self::isWpdb($file, $call, $call->receiver) || $call->arguments === []) {
            return [];
        }

        $query = self::parameter($call, 1, 'query');
        if ($query === null) {
            return [];
        }

        $self = new self($file, $identifierSupported);
        $self->walk($query->value);
        if ($self->textFound) {
            $self->checkCount($call);
        }

        return $self->findings;
    }

    private static function isWpdb(SourceFile $file, CallExpression $call, Node $receiver): bool
    {
        $receiver = Values::unwrap($file, $receiver);
        $text = $file->getText($receiver);
        if ($call->isStaticMethod()) {
            // WPCS: a static call only on the global class, not a namespaced one.
            return in_array(strtolower($text), ['wpdb', '\\wpdb'], strict: true);
        }

        return $receiver->kind === NodeKind::Variable && $text === '$wpdb';
    }

    /**
     * WPCS `getParameterFromStack()`: the argument named $name, or else the
     * positional argument at $position (1-based).
     */
    private static function parameter(CallExpression $call, int $position, string $name): ?CallArgument
    {
        foreach ($call->arguments as $argument) {
            if ($argument->name === $name) {
                return $argument;
            }
        }

        $argument = $call->arguments[$position - 1] ?? null;

        return $argument !== null && $argument->name === null ? $argument : null;
    }

    private function add(string $code, Node $node, string $found = ''): void
    {
        $this->findings[] = [$code, $node, $found];
    }

    private function checkCount(CallExpression $call): void
    {
        $total = count($call->arguments);
        if ($this->placeholders === 0) {
            if ($total === 1 && !$this->variableFound && !$this->wildcardFound) {
                $this->add('UnnecessaryPrepare', $call->node);
            }

            if ($total > 1 && $this->usesIn === 0) {
                $this->add('UnfinishedPrepare', $call->node);
            }

            return;
        }

        if ($total === 1) {
            $this->add('MissingReplacements', $call->node, (string) $this->placeholders);

            return;
        }

        // The replacements may come as one array in the variadic `$args`.
        $replacements = $total - 1;
        $args = self::parameter($call, 2, 'args');
        if ($args !== null && $total === 2) {
            $replacements = $this->arrayItems($args->value) ?? $replacements;
        }

        $expected = $this->placeholders - $this->adjustment;

        // WPCS bows out of `IN` clauses that appear to be correct.
        if ($this->usesIn > 0 && $this->usesIn === $this->implodeFill && $replacements === 1) {
            return;
        }

        if ($replacements !== $expected) {
            $this->add('ReplacementsWrongNumber', $call->node, "{$replacements}/{$expected}");
        }
    }

    /**
     * Counts the items of an array literal, a spread included. NULL when
     * the value does not start with one.
     */
    private function arrayItems(Node $value): ?int
    {
        if ($value->kind !== NodeKind::Array && $value->kind !== NodeKind::LegacyArray) {
            return null;
        }

        $count = 0;
        foreach ($this->file->getChildren($value) as $element) {
            $count += ($this->file->getChildren($element)[0] ?? $element)->kind === NodeKind::MissingArrayElement
                ? 0
                : 1;
        }

        return $count;
    }

    private function walk(Node $node): void
    {
        switch ($node->kind) {
            case NodeKind::LiteralString:
            case NodeKind::CompositeString:
                $this->text($node);

                return;
            case NodeKind::Variable:
                $this->variableFound = $this->variableFound || $this->file->getText($node) !== '$wpdb';

                return;
            case NodeKind::FunctionCall:
                if ($this->call($node)) {
                    return;
                }

                break;
            default:
                break;
        }

        foreach ($this->file->getChildren($node) as $child) {
            $this->walk($child);
        }
    }

    /**
     * Handles a `sprintf()` or `implode()` call in the query. TRUE when it
     * took care of the call's arguments.
     */
    private function call(Node $node): bool
    {
        $name = Calls::name($this->file, $node);
        $name = $name === null ? '' : Calls::normalize($name);
        if ($name === 'sprintf') {
            return $this->sprintf(CallExpression::fromNode($this->file, $node));
        }

        if ($name !== 'implode') {
            return false;
        }

        $previous = $this->previousText($node);
        if ($previous === null) {
            return false;
        }

        // An embed that ends the string is not `IN (`.
        $match = [];
        if (preg_match('`\s+IN\s*\(\s*(["\'])?$`i', $this->content($previous, self::GAP), $match) !== 1) {
            return false;
        }

        if (count($match) > 1) {
            $this->add('QuotedDynamicPlaceholderGeneration', $previous);
        }

        if (!$this->validImplode($node)) {
            return false;
        }

        ++$this->usesIn;
        ++$this->implodeFill;

        return true;
    }

    private function sprintf(CallExpression $call): bool
    {
        if ($call->arguments === []) {
            return false;
        }

        // WPCS cannot map named arguments of the variadic sprintf(), so it
        // skips the whole call.
        foreach ($call->arguments as $argument) {
            if ($argument->name !== null) {
                return true;
            }
        }

        // The format is query text; the values are skipped, except that an
        // implode() of an array_fill() among them counts as a valid `IN`.
        $this->walk($call->arguments[0]->value);
        foreach ($call->arguments as $index => $argument) {
            $value = Values::unwrap($this->file, $argument->value);
            if ($index === 0 || $value->kind !== NodeKind::FunctionCall) {
                continue;
            }

            $name = Calls::name($this->file, $value);
            if ($name !== null && Calls::normalize($name) === 'implode' && $this->validImplode($value)) {
                ++$this->implodeFill;
            }
        }

        $this->adjustment += count($call->arguments) - 1;

        return true;
    }

    /**
     * WPCS `analyse_implode()`: whether the call is
     * `implode(',', array_fill(..., '%s'))` with a simple placeholder.
     */
    private function validImplode(Node $node): bool
    {
        $implode = CallExpression::fromNode($this->file, $node);
        if (count($implode->arguments) !== 2) {
            return false;
        }

        $separator = self::parameter($implode, 1, 'separator');
        if ($separator === null || preg_match('`^(["\']), ?\1$`', $this->file->getText($separator->value)) !== 1) {
            return false;
        }

        $array = self::parameter($implode, 2, 'array');
        $fill = $array === null ? null : Values::unwrap($this->file, $array->value);
        $name = $fill === null || $fill->kind !== NodeKind::FunctionCall ? null : Calls::name($this->file, $fill);
        if ($fill === null || $name === null || Calls::normalize($name) !== 'array_fill') {
            return false;
        }

        $value = self::parameter(CallExpression::fromNode($this->file, $fill), 3, 'value');
        if ($value === null) {
            return false;
        }

        $clean = $this->file->getText($value->value);
        if ($clean === "'%i'" || $clean === '"%i"') {
            $this->add('IdentifierWithinIN', $value->value);

            return false;
        }

        return preg_match('`^(["\'])%[dfFs]\1$`', $clean) === 1;
    }

    /**
     * The string literal right before a call, across a `.`: the token WPCS
     * finds when it looks back from `implode`.
     */
    private function previousText(Node $node): ?Node
    {
        $child = $node;
        $parent = $this->file->getParent($child);
        while ($parent !== null && in_array($parent->kind, [NodeKind::Call, NodeKind::Expression], strict: true)) {
            $child = $parent;
            $parent = $this->file->getParent($child);
        }

        $children = $parent === null ? [] : $this->file->getChildren($parent);
        if (
            $parent?->kind !== NodeKind::Binary
            || ($children[2] ?? null) !== $child
            || $this->file->getText($children[1]) !== '.'
        ) {
            return null;
        }

        $left = $children[0];
        while (true) {
            $left = Values::unwrap($this->file, $left);
            if ($left->kind === NodeKind::LiteralString || $left->kind === NodeKind::CompositeString) {
                return $left;
            }

            $parts = $this->file->getChildren($left);
            if ($left->kind !== NodeKind::Binary || $this->file->getText($parts[1]) !== '.') {
                return null;
            }

            $left = $parts[2];
        }
    }

    /**
     * Checks one string literal, as WPCS checks one text string token.
     */
    private function text(Node $node): void
    {
        $this->textFound = true;
        if ($node->kind === NodeKind::CompositeString) {
            foreach (Strings::compositeParts($this->file, $node) as $part => $text) {
                $embed = $text === null ? $this->file->getText($part) : '$wpdb->';
                $this->variableFound = $this->variableFound || preg_match('`^\{?\$\{?wpdb\??->`', $embed) !== 1;
            }
        }

        $content = $this->content($node, '');
        $this->placeholders += (int) preg_match_all('`' . Placeholders::PLACEHOLDER . '`', $content);
        $content = $this->withoutLikeOperands($node, $content);
        if (str_contains($content, '%')) {
            $this->checkPlaceholders($node, $content);
        }
    }

    /**
     * Reports the SQL wildcards in each `LIKE` operand, then removes the
     * operand: its wildcards are not placeholders to validate. WPCS removes
     * the first occurrence of the operand text, wherever that is.
     */
    private function withoutLikeOperands(Node $node, string $content): string
    {
        $matches = [];
        if ((int) preg_match_all(Placeholders::LIKE, $content, $matches) === 0) {
            return $content;
        }

        // WPCS walks all quoted operands before the `CONCAT()` ones.
        foreach ([$matches[2], $matches[3]] as $operands) {
            foreach (array_filter($operands) as $index => $operand) {
                $code = match (true) {
                    !str_contains($operand, '%') && !str_contains($operand, '_') => 'LikeWithoutWildcards',
                    str_contains($operand, '%s') => 'LikeWildcardsInQueryWithPlaceholder',
                    default => 'LikeWildcardsInQuery',
                };
                $this->wildcardFound = $this->wildcardFound || $code !== 'LikeWithoutWildcards';
                $this->add($code, $node, $matches[0][$index]);
                $content = (string) preg_replace(
                    '`' . preg_quote($operand, delimiter: '`') . '`',
                    replacement: '',
                    subject: $content,
                    limit: 1,
                );
            }
        }

        return $content;
    }

    private function checkPlaceholders(Node $node, string $content): void
    {
        $matches = [];
        preg_match_all(Placeholders::UNSUPPORTED, $content, $matches);
        foreach ($matches[0] as $match) {
            $this->add($match === '%' ? 'UnescapedLiteral' : 'UnsupportedPlaceholder', $node, $match);
        }

        preg_match_all('`' . Placeholders::PLACEHOLDER . '`', $content, $matches);
        foreach ($matches[0] as $match) {
            if ($this->identifierSupported || !str_ends_with($match, 'i')) {
                continue;
            }

            $this->add('UnsupportedIdentifierPlaceholder', $node, $match);
        }

        preg_match_all('`(["\'])%[dfFs]\1`', $content, $matches);
        foreach ($matches[0] as $match) {
            $this->add('QuotedSimplePlaceholder', $node, $match);
        }

        preg_match_all('/(["\'`])(' . Placeholders::PLACEHOLDER . ')\1/', $content, $matches);
        foreach ($matches[2] as $index => $match) {
            if (!str_ends_with($match, 'i')) {
                continue;
            }

            $this->add('QuotedIdentifierPlaceholder', $node, $matches[0][$index]);
        }

        // WordPress quotes only the simple placeholders; `%i` never needs it.
        preg_match_all('`(?<!["\'])' . Placeholders::PLACEHOLDER . '(?!["\'])`', $content, $matches);
        foreach ($matches[0] as $match) {
            if (str_ends_with($match, 'i') || preg_match('`^%[dfFsi]$`', $match) === 1) {
                continue;
            }

            $this->add('UnquotedComplexPlaceholder', $node, $match);
        }

        $this->usesIn += (int) preg_match_all('`\s+IN\s*\(\s*%s\s*\)`i', $content);
    }

    /**
     * The text of a string literal, with $embed in place of each embed of
     * an interpolated string. WPCS strips the embeds before it checks the
     * text.
     */
    private function content(Node $node, string $embed): string
    {
        if ($node->kind === NodeKind::LiteralString) {
            return Values::literalString($this->file, $node) ?? '';
        }

        $content = '';
        foreach (Strings::compositeParts($this->file, $node) as $part => $text) {
            $content .= $text ?? $embed;
        }

        return $content;
    }
}
