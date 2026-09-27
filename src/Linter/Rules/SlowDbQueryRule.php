<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function in_array;

/**
 * Ports `WordPress.DB.SlowDBQuery`.
 */
final class SlowDbQueryRule implements Rule
{
    private const SET_QUERY_VAR = 'set_query_var';

    private readonly FileGate $gate;

    /** @var array<string, true> */
    private readonly array $wanted;

    public function __construct()
    {
        $this->gate = new FileGate('/meta_query|tax_query|meta_key|meta_value|set_query_var/i');
        $this->wanted = Calls::normalizeAll([self::SET_QUERY_VAR]);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/slow-db-query',
            name: 'Slow DB query',
            description: 'Flags query arguments that are known to produce slow database queries: meta_query, tax_query, '
            . 'meta_key, and meta_value used as array keys (or passed to set_query_var()). Meta and taxonomy queries run '
            . 'against unindexed columns in wp_postmeta, so they degrade badly as the site grows.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Array, NodeKind::LegacyArray, NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
            return;
        }

        match ($context->node->kind) {
            NodeKind::Array, NodeKind::LegacyArray => $this->checkArray($context),
            NodeKind::FunctionCall => $this->checkSetQueryVar($context),
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

    private function checkSetQueryVar(LintContext $context): void
    {
        $file = $context->file;
        if (Calls::matchWanted($file, $context->node, $this->wanted) === null) {
            return;
        }

        $call = CallExpression::fromNode($file, $context->node);
        $first = Calls::argument($file, $call, index: 0, parameter: 'query_var');
        if ($first !== null) {
            $this->checkKey($context, $first);
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
        if ($value === null || !in_array($value, Lists::SLOW_DB_QUERY_KEYS, strict: true)) {
            return;
        }

        $context->report(Issue::new(
            "Potentially slow database query using `{$value}`.",
            $node->span,
            'This query argument is slow at scale',
        )->withNote(
            'Meta and taxonomy queries run against unindexed columns, so they become very slow as the number of posts grows.',
        )->withHelp(
            'Prefer indexed alternatives: register a taxonomy for filterable values, use a dedicated table for complex lookups, or cache the query results.',
        ));
    }
}
