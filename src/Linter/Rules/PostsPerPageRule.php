<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function in_array;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Ports `WordPress.WP.PostsPerPage`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class PostsPerPageRule implements Rule
{
    private const SNIFF = 'WordPress.WP.PostsPerPage';

    private const NOPAGING_KEY = 'nopaging';

    private readonly FileGate $gate;

    private readonly int $max;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->max = $settings->maxPostsPerPage;
        // The escape branch keeps in a key spelled with a hex, unicode, or octal escape, like `"posts_per_pag\x65"`.
        $this->gate = new FileGate('/posts_per_page|numberposts|nopaging|\\\\(?:x[0-9a-f]|u\{|[0-7])/i');
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/posts-per-page',
            name: 'Posts per page',
            description: 'Flags query arguments that request an unbounded or excessively large number of posts: '
            . 'posts_per_page or numberposts set to -1 or to a value above the configured maximum (default 100, '
            . 'max-posts-per-page), and nopaging set to true. Unbounded '
            . 'queries load every matching row into memory and can take a site down as content grows.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Array, NodeKind::LegacyArray],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
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
        $file = $context->file;
        $children = $file->getChildren($element);
        $keyNode = $children[0] ?? null;
        $valueNode = $children[1] ?? null;
        if ($keyNode === null || $valueNode === null) {
            return;
        }

        $key = Values::literalString($file, Values::unparenthesize($file, $keyNode));
        if ($key === null) {
            return;
        }

        if (in_array($key, Lists::POSTS_PER_PAGE_KEYS, strict: true)) {
            $this->checkLimit($context, $key, $element, Values::unparenthesize($file, $valueNode));

            return;
        }

        if ($key === self::NOPAGING_KEY) {
            $this->checkNopaging($context, $element, Values::unparenthesize($file, $valueNode));
        }
    }

    /**
     * `posts_per_page` / `numberposts`: flags `-1` (unbounded) and anything
     * above the configured maximum.
     */
    private function checkLimit(LintContext $context, string $key, Node $element, Node $value): void
    {
        $number = $this->numericValue($context->file, $value);
        if ($number === -1) {
            $this->report->issue(
                $context,
                Issue::new(
                    "Unbounded query: `{$key}` is set to `-1`.",
                    $element->span,
                    'This query fetches every matching post',
                )->withNote(
                    'Unbounded queries load every matching row into memory and degrade badly as content grows.',
                )->withHelp('Paginate the query with a reasonable page size instead of fetching everything at once.'),
                [self::SNIFF . ".posts_per_page_{$key}"],
            );

            return;
        }

        if ($number !== null && $number > $this->max) {
            $this->report->issue(
                $context,
                Issue::new(
                    "Excessively large query: `{$key}` exceeds the maximum of {$this->max}.",
                    $element->span,
                    'This query fetches too many posts',
                )->withNote(
                    'Huge result sets load every matching row into memory and degrade badly as content grows.',
                )->withHelp('Paginate the query with a reasonable page size instead of fetching everything at once.'),
                [self::SNIFF . ".posts_per_page_{$key}"],
            );
        }
    }

    /**
     * `nopaging`: flags a literal `true`.
     */
    private function checkNopaging(LintContext $context, Node $element, Node $value): void
    {
        if (strtolower(trim($context->file->getText($value))) !== 'true') {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Unbounded query: `nopaging` is set to `true`.',
                $element->span,
                'This query fetches every matching post',
            )->withNote(
                'Disabling pagination loads every matching row into memory and degrades badly as content grows.',
            )->withHelp('Paginate the query with a reasonable page size instead of fetching everything at once.'),
            [self::SNIFF],
        );
    }

    /**
     * Reads a `-1`/integer/numeric-string value expression as an int, or NULL
     * when the expression is not one of those shapes.
     */
    private function numericValue(SourceFile $file, Node $value): ?int
    {
        if ($value->kind === NodeKind::UnaryPrefix) {
            $children = $file->getChildren($value);
            $operator = $children[0] ?? null;
            $operand = $children[1] ?? null;
            if ($operator === null || trim($file->getText($operator)) !== '-' || $operand === null) {
                return null;
            }

            $magnitude = Values::literalInteger($file, Values::unparenthesize($file, $operand));

            return $magnitude === null ? null : -$magnitude;
        }

        if ($value->kind === NodeKind::LiteralInteger) {
            return Values::literalInteger($file, $value);
        }

        if ($value->kind === NodeKind::LiteralString) {
            $text = trim(Values::literalString($file, $value) ?? '');

            return preg_match('/^-?\d+$/', $text) === 1 ? (int) $text : null;
        }

        return null;
    }
}
