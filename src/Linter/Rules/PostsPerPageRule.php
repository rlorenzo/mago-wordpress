<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function bindec;
use function hexdec;
use function in_array;
use function is_numeric;
use function ltrim;
use function octdec;
use function preg_match;
use function preg_match_all;
use function str_replace;
use function substr;
use function trim;

use const PREG_SET_ORDER;

/**
 * Ports `WordPress.WP.PostsPerPage`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PostsPerPageRule implements Rule
{
    private const SNIFF = 'WordPress.WP.PostsPerPage';

    private readonly FileGate $gate;

    private readonly int $max;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->max = $settings->maxPostsPerPage;
        // The escape branch keeps in a key spelled with a hex, unicode, or octal escape, like `"posts_per_pag\x65"`.
        $this->gate = new FileGate(['/posts_per_page|numberposts|\\\\(?:x[0-9a-f]|u\{|[0-7])/i']);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/posts-per-page',
            name: 'Posts per page',
            description: 'Flags query arguments that request an excessively large number of posts: '
            . 'posts_per_page or numberposts set to a value above the configured maximum (default 100, '
            . 'max-posts-per-page), in array literals, `$args[\'key\'] = ...` '
            . 'assignments, and query strings like `\'posts_per_page=999\'`. Huge result sets '
            . 'load every matching row into memory and can take a site down as content grows.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Array, NodeKind::LegacyArray, NodeKind::Assignment, NodeKind::LiteralString],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
            return;
        }

        if ($context->node->kind === NodeKind::Assignment) {
            $this->checkAssignment($context);

            return;
        }

        if ($context->node->kind === NodeKind::LiteralString) {
            $this->checkQueryString($context);

            return;
        }

        $file = $context->file;
        foreach ($file->getChildren($context->node) as $wrapper) {
            if ($wrapper->kind !== NodeKind::ArrayElement) {
                continue;
            }

            $element = $file->getChildren($wrapper)[0] ?? null;
            if ($element === null || $element->kind !== NodeKind::KeyValueArrayElement) {
                continue;
            }

            $this->checkElement($context, $element);
        }
    }

    private function checkElement(LintContext $context, Node $element): void
    {
        $children = $context->file->getChildren($element);
        $keyNode = $children[0] ?? null;
        $valueNode = $children[1] ?? null;
        if ($keyNode === null || $valueNode === null) {
            return;
        }

        $this->checkPair($context, $keyNode, $valueNode, $element->span);
    }

    /**
     * `$args['posts_per_page'] = 999;` and `??=`, reported on the key like WPCS.
     */
    private function checkAssignment(LintContext $context): void
    {
        $file = $context->file;
        [$target, $operator, $valueNode] = $file->getChildren($context->node) + [null, null, null];
        if ($target === null || $operator === null || $valueNode === null) {
            return;
        }

        $target = Values::unwrap($file, $target);
        if (
            $target->kind !== NodeKind::ArrayAccess
            || !in_array(trim($file->getText($operator)), ['=', '??='], strict: true)
        ) {
            return;
        }

        $keyNode = $file->getChildren($target)[1] ?? null;
        if ($keyNode !== null) {
            $this->checkPair($context, $keyNode, $valueNode, $keyNode->span);
        }
    }

    /**
     * Query strings like `'nopaging=true&posts_per_page=999'`; the last
     * duplicate key wins, as it does for WordPress.
     */
    private function checkQueryString(LintContext $context): void
    {
        $text = Values::literalString($context->file, $context->node) ?? '';
        $matches = [];
        if (preg_match_all('#(?:^|&)([a-z_]+)=([^&]*)#i', $text, $matches, PREG_SET_ORDER) === 0) {
            return;
        }

        $params = [];
        foreach ($matches as [, $key, $value]) {
            $params[$key] = $value;
        }

        foreach (Lists::POSTS_PER_PAGE_KEYS as $key) {
            $value = $params[$key] ?? '';
            if (preg_match('/^[+-]?\d+$/', $value) === 1) {
                $this->checkLimit($context, $key, $context->node->span, (int) $value);
            }
        }
    }

    private function checkPair(LintContext $context, Node $keyNode, Node $valueNode, Span $span): void
    {
        $file = $context->file;
        $key = Values::literalString($file, Values::unparenthesize($file, $keyNode));
        if ($key === null || !in_array($key, Lists::POSTS_PER_PAGE_KEYS, strict: true)) {
            return;
        }

        $number = $this->numericValue($file, Values::unparenthesize($file, $valueNode));
        if ($number !== null) {
            $this->checkLimit($context, $key, $span, $number);
        }
    }

    /**
     * `posts_per_page` / `numberposts`: flags anything above the configured
     * maximum.
     */
    private function checkLimit(LintContext $context, string $key, Span $span, int $number): void
    {
        if ($number > $this->max) {
            $this->report->issue(
                $context,
                Issue::new(
                    "Excessively large query: `{$key}` exceeds the maximum of {$this->max}.",
                    $span,
                    'This query fetches too many posts',
                )->withNote(
                    'Huge result sets load every matching row into memory and degrade badly as content grows.',
                )->withHelp('Paginate the query with a reasonable page size instead of fetching everything at once.'),
                [self::SNIFF . ".posts_per_page_{$key}"],
            );
        }
    }

    /**
     * Reads a signed number/numeric-string value expression as an int, or NULL
     * when the expression is not one of those shapes.
     */
    private function numericValue(SourceFile $file, Node $value): ?int
    {
        if ($value->kind === NodeKind::UnaryPrefix) {
            $children = $file->getChildren($value);
            $operator = trim($file->getText($children[0] ?? $value));
            $operand = $children[1] ?? null;
            if ($operator !== '-' && $operator !== '+' || $operand === null) {
                return null;
            }

            $magnitude = $this->numberLiteral($file, Values::unparenthesize($file, $operand));

            return $magnitude === null || $operator === '+' ? $magnitude : -$magnitude;
        }

        if ($value->kind === NodeKind::LiteralString) {
            $text = trim(Values::literalString($file, $value) ?? '');

            return preg_match('/^\d+$/', $text) === 1 ? (int) $text : null;
        }

        return $this->numberLiteral($file, $value);
    }

    /**
     * Reads an integer literal in any base, or a float literal truncated the
     * way WPCS's `(int)` cast does.
     */
    private function numberLiteral(SourceFile $file, Node $value): ?int
    {
        $text = str_replace(search: '_', replace: '', subject: trim($file->getText($value)));
        if ($value->kind === NodeKind::LiteralFloat) {
            return is_numeric($text) ? (int) (float) $text : null;
        }

        if ($value->kind !== NodeKind::LiteralInteger) {
            return null;
        }

        return (int) match (true) {
            preg_match('/^0x/i', $text) === 1 => hexdec(substr($text, offset: 2)),
            preg_match('/^0b/i', $text) === 1 => bindec(substr($text, offset: 2)),
            preg_match('/^0o?[0-7]+$/i', $text) === 1 => octdec(ltrim(substr($text, offset: 1), characters: 'oO')),
            default => $text,
        };
    }
}
