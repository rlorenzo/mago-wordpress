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
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function in_array;
use function preg_match_all;
use function trim;

/**
 * Ports `WordPress.DB.SlowDBQuery`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class SlowDbQueryRule implements Rule
{
    private const SNIFF = 'WordPress.DB.SlowDBQuery';

    private readonly FileGate $gate;

    public function __construct(
        private readonly Report $report,
    ) {
        $this->gate = new FileGate(['/meta_query|tax_query|meta_key|meta_value/i']);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/slow-db-query',
            name: 'Slow DB query',
            description: 'Flags query arguments that are known to produce slow database queries: meta_query, tax_query, '
            . 'meta_key, and meta_value used as array keys, `$args[\'key\'] = ...` assignments, and query strings like '
            . '`\'meta_key=color\'`. Meta and taxonomy queries run against unindexed columns in wp_postmeta, so they '
            . 'degrade badly as the site grows.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [
                NodeKind::Array,
                NodeKind::LegacyArray,
                NodeKind::Assignment,
                NodeKind::LiteralString,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
            return;
        }

        match ($context->node->kind) {
            NodeKind::Array, NodeKind::LegacyArray => $this->checkArray($context),
            NodeKind::Assignment => $this->checkAssignment($context),
            NodeKind::LiteralString => $this->checkQueryString($context),
            default => null,
        };
    }

    private function checkArray(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getChildren($context->node) as $wrapper) {
            if ($wrapper->kind !== NodeKind::ArrayElement) {
                continue;
            }

            $element = $file->getChildren($wrapper)[0] ?? null;
            if ($element === null || $element->kind !== NodeKind::KeyValueArrayElement) {
                continue;
            }

            $key = $file->getChildren($element)[0] ?? null;
            if ($key !== null) {
                $this->checkKey($context, $key);
            }
        }
    }

    /**
     * `$args['meta_key'] = 'color';` and `??=`, reported on the key like WPCS.
     */
    private function checkAssignment(LintContext $context): void
    {
        $file = $context->file;
        [$target, $operator] = $file->getChildren($context->node) + [null, null];
        if ($target === null || $operator === null) {
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
            $this->checkKey($context, $keyNode);
        }
    }

    /**
     * Query strings like `'foo=bar&meta_key=color'`, flagged whatever the
     * value, even an empty one.
     */
    private function checkQueryString(LintContext $context): void
    {
        $text = Values::literalString($context->file, $context->node) ?? '';
        $matches = [];
        if (preg_match_all('#(?:^|&)([a-z_]+)=[^&]*#i', $text, $matches) === 0) {
            return;
        }

        foreach (Lists::SLOW_DB_QUERY_KEYS as $key) {
            if (!in_array($key, $matches[1], strict: true)) {
                continue;
            }

            $this->reportKey($context, $key, $context->node->span);
        }
    }

    private function checkKey(LintContext $context, Node $node): void
    {
        $file = $context->file;
        $key = Values::unparenthesize($file, $node);
        if ($key->kind !== NodeKind::LiteralString) {
            return;
        }

        $value = Values::literalString($file, $key);
        if ($value !== null && in_array($value, Lists::SLOW_DB_QUERY_KEYS, strict: true)) {
            $this->reportKey($context, $value, $node->span);
        }
    }

    private function reportKey(LintContext $context, string $value, Span $span): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                "Potentially slow database query using `{$value}`.",
                $span,
                'This query argument is slow at scale',
            )->withNote(
                'Meta and taxonomy queries run against unindexed columns, so they become very slow as the number of posts grows.',
            )->withHelp(
                'Prefer indexed alternatives: register a taxonomy for filterable values, use a dedicated table for complex lookups, or cache the query results.',
            ),
            [self::SNIFF . ".slow_db_query_{$value}"],
        );
    }
}
