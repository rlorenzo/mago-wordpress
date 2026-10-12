<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\AssignmentBlocks;
use Rlorenzo\MagoWordPress\Internal\AssignmentPadding;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_values;
use function max;
use function sprintf;
use function str_repeat;

/**
 * Ports `Generic.Formatting.MultipleStatementAlignment` as WordPress-Core sets it (`maxPadding`
 * 40, a warning): the equals signs of adjacent assignments, constants and properties line up, the
 * ends of the operators aligned, unless that takes more than 40 spaces of padding, where the block
 * ends and a new one starts. `mago format` aligns them however far apart, so this runs after it, as
 * `mago lint --fix --only wordpress/statement-alignment` (a step of `mago-wordpress format`); it is
 * off by default.
 */
final class StatementAlignmentRule implements Rule
{
    private const SNIFF = 'Generic.Formatting.MultipleStatementAlignment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/statement-alignment',
            name: 'Statement alignment',
            description: 'Aligns the equals signs of adjacent assignments with at most 40 spaces of padding, as WordPress-Core does. Off by default; run after `mago format`.',
            defaultLevel: Level::Warning,
            defaultEnabled: false,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $containers = [];
        foreach ([NodeKind::Statement, NodeKind::ClassLikeMember] as $kind) {
            foreach (NodeIndex::ofKind($file, $context->node, $kind) as $node) {
                $parent = $file->getParent($node);
                if ($parent instanceof Node) {
                    $containers[$parent->id] = $parent;
                }
            }
        }

        foreach (AssignmentBlocks::of($file, array_values($containers)) as $block) {
            foreach (AssignmentPadding::problems($block) as $problem) {
                $this->report($context, $block[$problem['index']], $problem);
            }
        }
    }

    /**
     * @param array{varEnd: int, length: int, found: int, gap: \Mago\Sdk\Span, operator: \Mago\Sdk\Span} $assignment
     * @param array{index: int, expected: int, found: int, single: bool} $problem
     */
    private function report(LintContext $context, array $assignment, array $problem): void
    {
        $message = $problem['single']
            ? 'Equals sign not aligned correctly; expected %s but found %s'
            : 'Equals sign not aligned with surrounding assignments; expected %s but found %s';
        $this->report->issue(
            $context,
            Issue::new(
                sprintf($message, self::spaces($problem['expected']), self::spaces($problem['found'])),
                $assignment['operator'],
            )->withEdit(TextEdit::replace($assignment['gap'], str_repeat(' ', max(0, $problem['expected'])))),
            [self::SNIFF . ($problem['single'] ? '.IncorrectWarning' : '.NotSameWarning')],
        );
    }

    private static function spaces(int $count): string
    {
        return $count . ($count === 1 ? ' space' : ' spaces');
    }
}
