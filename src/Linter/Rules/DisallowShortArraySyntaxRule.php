<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;

/**
 * Ports `Universal.Arrays.DisallowShortArraySyntax`: `[1, 2]` where WordPress writes `array( 1, 2 )`.
 * A short list (`[$a, $b] = ...`, `foreach ( $x as [ $a ] )`, and a list nested in one) is not an
 * array and is left alone, but an array used as a key in a short list is one. The fix is phpcbf's:
 * `[` becomes `array(` and `]` becomes `)`.
 */
final class DisallowShortArraySyntaxRule implements Rule
{
    private const CODE = 'Universal.Arrays.DisallowShortArraySyntax.Found';

    private const TRANSPARENT = [
        NodeKind::Expression,
        NodeKind::ArrayElement,
        NodeKind::ValueArrayElement,
        NodeKind::KeyValueArrayElement,
        NodeKind::Array,
        NodeKind::List,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-short-array-syntax',
            name: 'Disallow short array syntax',
            description: 'Reports `[...]` array literals; WordPress writes `array( ... )`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The assignment and foreach targets are in the snapshot for their subtrees only: a short
            // list is told from an array by its parents, which a snapshot holds only below a target.
            targets: [
                NodeKind::Array,
                NodeKind::Assignment,
                NodeKind::ForeachValueTarget,
                NodeKind::ForeachKeyValueTarget,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $array = $context->node;
        if ($array->kind !== NodeKind::Array) {
            return; // an assignment or foreach target, here only so that a short list in it has its parents
        }

        $file = $context->file;
        if (self::isShortList($file, $array)) {
            return;
        }

        $open = $array->span->start;
        $close = $array->span->end - 1;
        $this->report->issue(
            $context,
            Issue::new('Short array syntax is not allowed', new Span($open, $open + 1), 'short array')
                ->withHelp('Write `array( ... )`.')
                ->withEdit(TextEdit::replace(new Span($open, $open + 1), 'array('))
                ->withEdit(TextEdit::replace(new Span($close, $close + 1), ')')),
            [self::CODE],
        );
    }

    /**
     * Whether the array is a destructuring target: the left side of an assignment, the value of a
     * `foreach`, or nested in either as a value. An array used as a key inside one is an array.
     */
    private static function isShortList(SourceFile $file, Node $array): bool
    {
        $child = $array;
        $parent = $file->getParent($child);
        while ($parent !== null && in_array($parent->kind, self::TRANSPARENT, strict: true)) {
            $key = $parent->kind === NodeKind::KeyValueArrayElement ? $file->getChildren($parent)[0] ?? null : null;
            if ($key !== null && $key->span->start === $child->span->start && $key->span->end === $child->span->end) {
                return false;
            }

            $child = $parent;
            $parent = $file->getParent($child);
        }

        if ($parent === null) {
            return false;
        }

        if (in_array($parent->kind, [NodeKind::ForeachValueTarget, NodeKind::ForeachKeyValueTarget], strict: true)) {
            return true;
        }

        $target = $parent->kind === NodeKind::Assignment ? $file->getChildren($parent)[0] ?? null : null;

        return (
            $target !== null
            && $target->span->start === $child->span->start
            && $target->span->end === $child->span->end
        );
    }
}
